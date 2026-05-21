<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\Product;
use Freeline\Pdv\Models\PurchaseOrder;
use Freeline\Pdv\Models\PurchaseOrderItem;
use Freeline\Pdv\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseOrdersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::query()
            ->with(['supplier:id,nome'])
            ->orderByDesc('numero')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $status = mb_strtolower($request->string('status')->toString());
            if (in_array($status, [PurchaseOrder::STATUS_OPEN, PurchaseOrder::STATUS_RECEIVED], true)) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('search')) {
            $needle = mb_strtolower($request->string('search')->toString());
            $numericNeedle = preg_replace('/\D+/', '', $needle);
            $query->where(function ($builder) use ($needle, $numericNeedle): void {
                if ($numericNeedle !== '') {
                    $builder->where('numero', (int) $numericNeedle);
                    $builder->orWhereHas('supplier', function ($supplierQuery) use ($needle): void {
                        $supplierQuery->whereRaw('LOWER(nome) like ?', ["%{$needle}%"]);
                    });

                    return;
                }

                $builder->whereHas('supplier', function ($supplierQuery) use ($needle): void {
                    $supplierQuery->whereRaw('LOWER(nome) like ?', ["%{$needle}%"]);
                });
            });
        }

        return response()->json(
            $query
                ->get()
                ->map(fn (PurchaseOrder $order) => $this->presentSummary($order))
                ->values(),
        );
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json($this->presentDetail($this->loadOrder($purchaseOrder->id)));
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $operatorId = $request->user()?->id;

        $order = DB::transaction(function () use ($payload, $operatorId): PurchaseOrder {
            $nextNumber = $this->resolveNextNumber();

            $order = PurchaseOrder::query()->create([
                'numero' => $nextNumber,
                'supplier_id' => $payload['supplier_id'],
                'data_compra' => $payload['data_compra'],
                'filial' => $this->nullableTrim($payload['filial'] ?? null),
                'status' => PurchaseOrder::STATUS_OPEN,
                'observacoes' => $this->nullableTrim($payload['observacoes'] ?? null),
                'created_by' => $operatorId,
            ]);

            $this->persistItems($order, $payload['items']);

            return $order;
        });

        return response()->json($this->presentDetail($this->loadOrder($order->id)), 201);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $payload = $this->validatePayload($request);

        DB::transaction(function () use ($purchaseOrder, $payload): void {
            $order = PurchaseOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if ($order->status !== PurchaseOrder::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'status' => ['Somente compras em aberto podem ser editadas.'],
                ]);
            }

            $order->supplier_id = $payload['supplier_id'];
            $order->data_compra = $payload['data_compra'];
            $order->filial = $this->nullableTrim($payload['filial'] ?? null);
            $order->observacoes = $this->nullableTrim($payload['observacoes'] ?? null);
            $order->save();

            PurchaseOrderItem::query()
                ->where('purchase_order_id', $order->id)
                ->delete();

            $this->persistItems($order, $payload['items']);
        });

        return response()->json($this->presentDetail($this->loadOrder($purchaseOrder->id)));
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $operatorId = $request->user()?->id;

        DB::transaction(function () use ($purchaseOrder, $operatorId): void {
            $order = PurchaseOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if ($order->status !== PurchaseOrder::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'status' => ['Esta compra já foi recebida.'],
                ]);
            }

            foreach ($order->items as $item) {
                $remaining = round((float) $item->quantidade - (float) $item->quantidade_recebida, 3);
                if ($remaining <= 0.000001 || ! $item->product_id) {
                    $item->quantidade_recebida = $item->quantidade;
                    $item->save();
                    continue;
                }

                $product = Product::query()->lockForUpdate()->find($item->product_id);
                if (! $product) {
                    $item->quantidade_recebida = $item->quantidade;
                    $item->save();
                    continue;
                }

                $beforeStock = round((float) $product->estoque_atual, 3);
                $movementQuantity = round($remaining, 3);
                $afterStock = round($beforeStock + $movementQuantity, 3);

                $product->estoque_atual = $afterStock;
                $product->save();

                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'stock_adjustment_id' => null,
                    'tipo' => 'entrada',
                    'origem' => 'compra',
                    'referencia' => 'Compra #'.$order->numero,
                    'quantidade_anterior' => $beforeStock,
                    'quantidade_movimentada' => $movementQuantity,
                    'quantidade_atual' => $afterStock,
                    'descricao' => 'Recebimento de compra',
                    'happened_at' => now(),
                    'created_by' => $operatorId,
                ]);

                $item->quantidade_recebida = $item->quantidade;
                $item->save();
            }

            $order->status = PurchaseOrder::STATUS_RECEIVED;
            $order->received_at = now();
            $order->received_by = $operatorId;
            $order->save();
        });

        return response()->json($this->presentDetail($this->loadOrder($purchaseOrder->id)));
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'],
            'data_compra' => ['required', 'date'],
            'filial' => ['nullable', 'string', 'max:120'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantidade' => ['required', 'numeric', 'gt:0'],
            'items.*.custo_unitario' => ['required', 'numeric', 'gte:0'],
        ]);
    }

    private function loadOrder(string $id): PurchaseOrder
    {
        return PurchaseOrder::query()
            ->with([
                'supplier:id,nome,documento,telefone,email',
                'creator:id,name',
                'receiver:id,name',
                'items.product:id,nome,codigo,unidade',
            ])
            ->findOrFail($id);
    }

    private function persistItems(PurchaseOrder $order, array $rawItems): void
    {
        $rows = [];
        $totalQuantity = 0.0;
        $totalValue = 0.0;

        $products = Product::query()
            ->whereIn('id', collect($rawItems)->pluck('product_id')->filter()->values())
            ->get(['id', 'nome', 'codigo'])
            ->keyBy('id');

        foreach ($rawItems as $item) {
            $productId = (string) ($item['product_id'] ?? '');
            $product = $products->get($productId);
            if (! $product) {
                continue;
            }

            $quantity = round((float) $item['quantidade'], 3);
            $unitCost = round((float) $item['custo_unitario'], 2);
            $lineTotal = round($quantity * $unitCost, 2);

            $rows[] = [
                'id' => (string) Str::uuid(),
                'purchase_order_id' => $order->id,
                'product_id' => $product->id,
                'produto_nome' => $product->nome,
                'produto_codigo' => $product->codigo ?: null,
                'quantidade' => $quantity,
                'quantidade_recebida' => 0,
                'custo_unitario' => $unitCost,
                'total' => $lineTotal,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $totalQuantity = round($totalQuantity + $quantity, 3);
            $totalValue = round($totalValue + $lineTotal, 2);
        }

        if (! count($rows)) {
            throw ValidationException::withMessages([
                'items' => ['Adicione ao menos um item válido na compra.'],
            ]);
        }

        PurchaseOrderItem::query()->insert($rows);

        $order->total_items = count($rows);
        $order->total_quantity = $totalQuantity;
        $order->total_value = $totalValue;
        $order->save();
    }

    private function resolveNextNumber(): int
    {
        $last = PurchaseOrder::query()
            ->select('numero')
            ->orderByDesc('numero')
            ->lockForUpdate()
            ->first();

        return max(1, (int) ($last?->numero ?? 0) + 1);
    }

    private function nullableTrim(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function presentSummary(PurchaseOrder $order): array
    {
        return [
            'id' => $order->id,
            'numero' => (int) $order->numero,
            'supplier_id' => $order->supplier_id,
            'supplier_name' => (string) ($order->supplier?->nome ?? 'Fornecedor removido'),
            'data_compra' => $order->data_compra?->toDateString(),
            'filial' => $order->filial,
            'status' => $order->status,
            'status_label' => $order->status === PurchaseOrder::STATUS_RECEIVED ? 'Recebido' : 'Em aberto',
            'total_items' => (int) $order->total_items,
            'total_quantity' => $order->total_quantity,
            'total_value' => $order->total_value,
            'created_at' => $order->created_at,
            'received_at' => $order->received_at,
            'can_edit' => $order->status === PurchaseOrder::STATUS_OPEN,
            'can_receive' => $order->status === PurchaseOrder::STATUS_OPEN,
        ];
    }

    private function presentDetail(PurchaseOrder $order): array
    {
        return [
            ...$this->presentSummary($order),
            'observacoes' => $order->observacoes,
            'supplier' => $order->supplier ? [
                'id' => $order->supplier->id,
                'nome' => $order->supplier->nome,
                'documento' => $order->supplier->documento,
                'telefone' => $order->supplier->telefone,
                'email' => $order->supplier->email,
            ] : null,
            'creator' => $order->creator ? [
                'id' => $order->creator->id,
                'name' => $order->creator->name,
            ] : null,
            'receiver' => $order->receiver ? [
                'id' => $order->receiver->id,
                'name' => $order->receiver->name,
            ] : null,
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'id' => $item->id,
                'purchase_order_id' => $item->purchase_order_id,
                'product_id' => $item->product_id,
                'produto_nome' => $item->produto_nome,
                'produto_codigo' => $item->produto_codigo,
                'quantidade' => $item->quantidade,
                'quantidade_recebida' => $item->quantidade_recebida,
                'custo_unitario' => $item->custo_unitario,
                'total' => $item->total,
            ])->values(),
        ];
    }
}
