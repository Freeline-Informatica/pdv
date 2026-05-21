<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\Product;
use Freeline\Pdv\Models\Produto;
use Freeline\Pdv\Models\StockAdjustment;
use Freeline\Pdv\Models\StockInventory;
use Freeline\Pdv\Models\StockInventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockInventoriesController extends Controller
{
    private const EPSILON = 0.000001;

    public function index(): JsonResponse
    {
        $items = StockInventory::query()
            ->with([
                'creator:id,name',
                'submitter:id,name',
            ])
            ->withCount([
                'items as total_items',
                'items as counted_items' => fn ($query) => $query->whereNotNull('quantidade_contada'),
                'items as divergent_items' => fn ($query) => $query
                    ->whereNotNull('quantidade_contada')
                    ->whereRaw('ABS(COALESCE(diferenca, 0)) > ?', [self::EPSILON]),
            ])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(
            $items
                ->map(fn (StockInventory $inventory) => $this->presentInventorySummary($inventory))
                ->values(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        $inventory = DB::transaction(function () use ($payload, $request): StockInventory {
            $inventory = StockInventory::query()->create([
                'status' => StockInventory::STATUS_OPEN,
                'observacoes' => $payload['observacoes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $catalogProducts = $this->catalogInventoryProducts();
            $this->syncLegacyInventoryProductMirror($catalogProducts);

            if ($catalogProducts->isNotEmpty()) {
                $now = now();
                $rows = $catalogProducts->map(fn (array $product) => [
                    'id' => (string) Str::uuid(),
                    'stock_inventory_id' => $inventory->id,
                    'product_id' => $product['id'],
                    'quantidade_sistema' => round((float) $product['estoque_atual'], 3),
                    'quantidade_contada' => null,
                    'diferenca' => null,
                    'saved_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                StockInventoryItem::query()->insert($rows->all());
            }

            return $inventory;
        });

        return response()->json(
            $this->presentInventoryDetail($this->loadInventoryWithItems($inventory->id)),
            201,
        );
    }

    public function show(StockInventory $stockInventory): JsonResponse
    {
        $this->refreshInventoryItemsFromCatalogWhenPristine($stockInventory->id);

        return response()->json(
            $this->presentInventoryDetail($this->loadInventoryWithItems($stockInventory->id)),
        );
    }

    public function updateItem(
        Request $request,
        StockInventory $stockInventory,
        StockInventoryItem $stockInventoryItem
    ): JsonResponse {
        $payload = $request->validate([
            'quantidade_contada' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($stockInventory, $stockInventoryItem, $payload): void {
            $inventory = StockInventory::query()
                ->lockForUpdate()
                ->findOrFail($stockInventory->id);

            if ($inventory->status === StockInventory::STATUS_FINALIZED) {
                throw ValidationException::withMessages([
                    'status' => ['Inventários finalizados não podem ser alterados.'],
                ]);
            }

            $item = StockInventoryItem::query()
                ->where('stock_inventory_id', $inventory->id)
                ->lockForUpdate()
                ->findOrFail($stockInventoryItem->id);

            $countedQuantity = round((float) $payload['quantidade_contada'], 3);
            $systemQuantity = round((float) $item->quantidade_sistema, 3);
            $difference = round($countedQuantity - $systemQuantity, 3);

            $item->quantidade_contada = $countedQuantity;
            $item->diferenca = $difference;
            $item->saved_at = now();
            $item->save();

            if ($inventory->status === StockInventory::STATUS_OPEN) {
                $inventory->status = StockInventory::STATUS_IN_PROGRESS;
                $inventory->save();
            }
        });

        return response()->json(
            $this->presentInventoryDetail($this->loadInventoryWithItems($stockInventory->id)),
        );
    }

    public function sendToAdjustments(Request $request, StockInventory $stockInventory): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $stockInventory): array {
            $inventory = StockInventory::query()
                ->lockForUpdate()
                ->findOrFail($stockInventory->id);

            if ($inventory->status === StockInventory::STATUS_FINALIZED) {
                throw ValidationException::withMessages([
                    'status' => ['Este inventário já foi enviado para ajustes.'],
                ]);
            }

            $countedItems = StockInventoryItem::query()
                ->where('stock_inventory_id', $inventory->id)
                ->whereNotNull('quantidade_contada')
                ->get();

            if ($countedItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['Salve ao menos uma contagem antes de enviar para ajustes.'],
                ]);
            }

            $divergentItems = $countedItems
                ->filter(fn (StockInventoryItem $item) => abs((float) $item->diferenca) > self::EPSILON)
                ->values();

            if ($divergentItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['Nenhuma divergência encontrada para gerar solicitações de ajuste.'],
                ]);
            }

            $createdAdjustments = 0;
            $firstAdjustmentId = null;

            foreach ($divergentItems as $item) {
                $adjustment = StockAdjustment::query()->create([
                    'stock_inventory_id' => $inventory->id,
                    'product_id' => $item->product_id,
                    'tipo' => 'inventario',
                    'status' => StockAdjustment::STATUS_PENDING,
                    'quantidade_atual' => round((float) $item->quantidade_sistema, 3),
                    'nova_quantidade' => round((float) $item->quantidade_contada, 3),
                    'diferenca' => round((float) $item->diferenca, 3),
                    'complemento' => $this->buildAdjustmentNote($inventory, $item),
                    'requested_by' => $request->user()?->id,
                ]);

                $createdAdjustments++;
                $firstAdjustmentId ??= $adjustment->id;
            }

            $inventory->status = StockInventory::STATUS_FINALIZED;
            $inventory->submitted_at = now();
            $inventory->submitted_by = $request->user()?->id;
            $inventory->submitted_adjustments_count = $createdAdjustments;
            $inventory->save();

            return [
                'created_adjustments' => $createdAdjustments,
                'first_adjustment_id' => $firstAdjustmentId,
            ];
        });

        return response()->json([
            'message' => $result['created_adjustments'] === 1
                ? '1 solicitação enviada para ajustes.'
                : $result['created_adjustments'].' solicitações enviadas para ajustes.',
            'created_adjustments' => $result['created_adjustments'],
            'first_adjustment_id' => $result['first_adjustment_id'],
            'inventory_id' => $stockInventory->id,
        ]);
    }

    private function loadInventoryWithItems(string $inventoryId): StockInventory
    {
        return StockInventory::query()
            ->with([
                'creator:id,name',
                'submitter:id,name',
                'items.product:id,nome,codigo,unidade,ativo',
            ])
            ->findOrFail($inventoryId);
    }

    private function presentInventorySummary(StockInventory $inventory): array
    {
        $totalItems = (int) ($inventory->total_items ?? $inventory->items?->count() ?? 0);
        $countedItems = (int) ($inventory->counted_items ?? $inventory->items?->whereNotNull('quantidade_contada')->count() ?? 0);
        $divergentItems = (int) (
            $inventory->divergent_items
            ?? $inventory->items?->filter(
                fn (StockInventoryItem $item) => $item->quantidade_contada !== null && abs((float) $item->diferenca) > self::EPSILON,
            )->count()
            ?? 0
        );

        return [
            'id' => $inventory->id,
            'status' => $inventory->status,
            'status_label' => $this->statusLabel($inventory->status),
            'observacoes' => $inventory->observacoes,
            'created_at' => $inventory->created_at,
            'updated_at' => $inventory->updated_at,
            'submitted_at' => $inventory->submitted_at,
            'submitted_adjustments_count' => (int) ($inventory->submitted_adjustments_count ?? 0),
            'creator' => $inventory->creator ? [
                'id' => $inventory->creator->id,
                'name' => $inventory->creator->name,
            ] : null,
            'submitter' => $inventory->submitter ? [
                'id' => $inventory->submitter->id,
                'name' => $inventory->submitter->name,
            ] : null,
            'summary' => [
                'total_items' => $totalItems,
                'counted_items' => $countedItems,
                'divergent_items' => $divergentItems,
            ],
        ];
    }

    private function presentInventoryDetail(StockInventory $inventory): array
    {
        $items = $inventory->items
            ->sortBy(fn (StockInventoryItem $item) => mb_strtolower((string) ($item->product?->nome ?? '')))
            ->values();

        $summary = $this->presentInventorySummary($inventory)['summary'];

        return [
            ...$this->presentInventorySummary($inventory),
            'can_send_to_adjustments' => $inventory->status !== StockInventory::STATUS_FINALIZED && $summary['divergent_items'] > 0,
            'items' => $items->map(fn (StockInventoryItem $item) => [
                'id' => $item->id,
                'stock_inventory_id' => $item->stock_inventory_id,
                'product_id' => $item->product_id,
                'quantidade_sistema' => $item->quantidade_sistema,
                'quantidade_contada' => $item->quantidade_contada,
                'diferenca' => $item->diferenca,
                'saved_at' => $item->saved_at,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'nome' => $item->product->nome,
                    'codigo' => $item->product->codigo,
                    'unidade' => $item->product->unidade,
                    'ativo' => (bool) $item->product->ativo,
                ] : null,
            ])->values(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            StockInventory::STATUS_OPEN => 'Aberto',
            StockInventory::STATUS_IN_PROGRESS => 'Em andamento',
            StockInventory::STATUS_FINALIZED => 'Finalizado',
            default => 'Inventário',
        };
    }

    private function buildAdjustmentNote(StockInventory $inventory, StockInventoryItem $item): string
    {
        $inventoryRef = strtoupper(substr((string) $inventory->id, 0, 8));

        $parts = [
            'Inventário '.$inventoryRef,
            $this->formatDecimal((float) $item->quantidade_sistema).' -> '.$this->formatDecimal((float) $item->quantidade_contada),
        ];

        $notes = trim((string) $inventory->observacoes);
        if ($notes !== '') {
            $parts[] = $notes;
        }

        return implode(' | ', $parts);
    }

    private function formatDecimal(float $value): string
    {
        $normalized = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
        return $normalized === '' ? '0' : $normalized;
    }

    private function catalogInventoryProducts(): Collection
    {
        return Produto::query()
            ->with([
                'estoque:id,produto_id,quantidade,quantidade_minima',
                'unidadeMedida:id,unidade',
                'codigosBarras:id,produto_id,codigo,principal',
            ])
            ->where(function ($builder): void {
                $builder->whereNull('situacao')
                    ->orWhereRaw('LOWER(situacao) = ?', ['ativo']);
            })
            ->where(function ($builder): void {
                $builder->whereNull('liberado')
                    ->orWhereRaw('LOWER(liberado) = ?', ['sim']);
            })
            ->orderBy('descricao')
            ->get()
            ->map(function (Produto $produto): array {
                $barcode = $produto->codigosBarras->firstWhere('principal', true)
                    ?? $produto->codigosBarras->first();

                return [
                    'id' => (string) $produto->id,
                    'nome' => (string) ($produto->descricao ?: $produto->descricao_curta ?: 'Produto sem descrição'),
                    'codigo' => (string) ($produto->cod_sku ?: $produto->codigo_operacional ?: ($barcode?->codigo ?: '')),
                    'unidade' => (string) ($produto->unidadeMedida?->unidade ?: 'UN'),
                    'estoque_atual' => (float) ($produto->estoque?->quantidade ?? 0),
                    'estoque_minimo' => (float) ($produto->estoque?->quantidade_minima ?? 0),
                    'ativo' => true,
                ];
            })
            ->values();
    }

    private function syncLegacyInventoryProductMirror(Collection $catalogProducts): void
    {
        if ($catalogProducts->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $catalogProducts
            ->map(fn (array $product): array => [
                'id' => $product['id'],
                'nome' => $product['nome'],
                'codigo' => $product['codigo'] !== '' ? $product['codigo'] : null,
                'preco_venda' => 0,
                'preco_custo' => 0,
                'unidade' => $product['unidade'],
                'estoque_atual' => round((float) $product['estoque_atual'], 3),
                'estoque_minimo' => round((float) $product['estoque_minimo'], 3),
                'category_id' => null,
                'ativo' => true,
                'observacoes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        Product::query()->upsert(
            $rows,
            ['id'],
            ['nome', 'codigo', 'unidade', 'estoque_atual', 'estoque_minimo', 'ativo', 'updated_at'],
        );
    }

    private function refreshInventoryItemsFromCatalogWhenPristine(string $inventoryId): void
    {
        DB::transaction(function () use ($inventoryId): void {
            $inventory = StockInventory::query()
                ->lockForUpdate()
                ->findOrFail($inventoryId);

            if ($inventory->status !== StockInventory::STATUS_OPEN) {
                return;
            }

            $hasCountedItems = StockInventoryItem::query()
                ->where('stock_inventory_id', $inventory->id)
                ->whereNotNull('quantidade_contada')
                ->exists();

            if ($hasCountedItems) {
                return;
            }

            $catalogProducts = $this->catalogInventoryProducts();
            $this->syncLegacyInventoryProductMirror($catalogProducts);

            StockInventoryItem::query()
                ->where('stock_inventory_id', $inventory->id)
                ->delete();

            if ($catalogProducts->isEmpty()) {
                return;
            }

            $now = now();
            $rows = $catalogProducts->map(fn (array $product) => [
                'id' => (string) Str::uuid(),
                'stock_inventory_id' => $inventory->id,
                'product_id' => $product['id'],
                'quantidade_sistema' => round((float) $product['estoque_atual'], 3),
                'quantidade_contada' => null,
                'diferenca' => null,
                'saved_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            StockInventoryItem::query()->insert($rows->all());
        });
    }
}
