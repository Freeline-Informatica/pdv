<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\Product;
use Freeline\Pdv\Models\StockAdjustment;
use Freeline\Pdv\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StockAdjustment::query()
            ->with([
                'product:id,nome,codigo,unidade,estoque_atual,estoque_minimo,category_id',
                'product.category:id,nome',
                'requester:id,name',
                'resolver:id,name',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->string('product_id')->toString());
        }

        if ($request->filled('search')) {
            $needle = mb_strtolower($request->string('search')->toString());
            $query->where(function ($builder) use ($needle): void {
                $builder->whereRaw('LOWER(COALESCE(complemento, \'\')) like ?', ["%{$needle}%"])
                    ->orWhereHas('product', function ($productQuery) use ($needle): void {
                        $productQuery->whereRaw('LOWER(nome) like ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(codigo, \'\')) like ?', ["%{$needle}%"]);
                    });
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'tipo' => ['required', 'string', 'in:correcao,avaria,quebra,entrada,saida,inventario,outro'],
            'nova_quantidade' => ['required', 'numeric', 'min:0'],
            'complemento' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = Product::query()->findOrFail($payload['product_id']);
        $currentQuantity = round((float) $product->estoque_atual, 3);
        $newQuantity = round((float) $payload['nova_quantidade'], 3);

        if (abs($newQuantity - $currentQuantity) < 0.000001) {
            throw ValidationException::withMessages([
                'nova_quantidade' => ['A nova quantidade deve ser diferente da atual.'],
            ]);
        }

        $record = StockAdjustment::query()->create([
            'product_id' => $product->id,
            'tipo' => $payload['tipo'],
            'status' => StockAdjustment::STATUS_PENDING,
            'quantidade_atual' => $currentQuantity,
            'nova_quantidade' => $newQuantity,
            'diferenca' => round($newQuantity - $currentQuantity, 3),
            'complemento' => $payload['complemento'] ?? null,
            'requested_by' => $request->user()?->id,
        ]);

        return response()->json(
            $record->load([
                'product:id,nome,codigo,unidade,estoque_atual,estoque_minimo,category_id',
                'product.category:id,nome',
                'requester:id,name',
                'resolver:id,name',
            ]),
            201,
        );
    }

    public function update(Request $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        $payload = $request->validate([
            'tipo' => ['sometimes', 'string', 'in:correcao,avaria,quebra,entrada,saida,inventario,outro'],
            'nova_quantidade' => ['sometimes', 'numeric', 'min:0'],
            'complemento' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', 'in:pendente,aprovado,rejeitado,cancelado'],
        ]);

        $actorId = $request->user()?->id;

        $updated = DB::transaction(function () use ($payload, $stockAdjustment, $actorId) {
            $record = StockAdjustment::query()
                ->with('product')
                ->lockForUpdate()
                ->findOrFail($stockAdjustment->id);

            $currentStatus = $record->status;
            $nextStatus = $payload['status'] ?? $record->status;

            $isChangingEditableFields = array_key_exists('tipo', $payload)
                || array_key_exists('nova_quantidade', $payload)
                || array_key_exists('complemento', $payload);

            if ($currentStatus !== StockAdjustment::STATUS_PENDING && $isChangingEditableFields) {
                throw ValidationException::withMessages([
                    'status' => ['Somente ajustes pendentes podem ser editados.'],
                ]);
            }

            if ($currentStatus !== StockAdjustment::STATUS_PENDING && $nextStatus !== $currentStatus) {
                throw ValidationException::withMessages([
                    'status' => ['Somente ajustes pendentes podem mudar de status.'],
                ]);
            }

            if (array_key_exists('tipo', $payload)) {
                $record->tipo = $payload['tipo'];
            }

            if (array_key_exists('complemento', $payload)) {
                $record->complemento = $payload['complemento'];
            }

            if (array_key_exists('nova_quantidade', $payload)) {
                $record->nova_quantidade = round((float) $payload['nova_quantidade'], 3);
            }

            if ($nextStatus === StockAdjustment::STATUS_APPROVED && $record->status !== StockAdjustment::STATUS_APPROVED) {
                $this->applyStockAdjustment($record, $actorId);
            } elseif (
                in_array($nextStatus, [StockAdjustment::STATUS_REJECTED, StockAdjustment::STATUS_CANCELED], true)
                && $nextStatus !== $record->status
            ) {
                $record->status = $nextStatus;
                $record->resolved_by = $actorId;
                $record->resolved_at = now();
            } elseif (
                $nextStatus === StockAdjustment::STATUS_PENDING
                && $record->status === StockAdjustment::STATUS_PENDING
            ) {
                $currentQuantity = round((float) $record->quantidade_atual, 3);
                $newQuantity = round((float) $record->nova_quantidade, 3);

                if (abs($newQuantity - $currentQuantity) < 0.000001) {
                    throw ValidationException::withMessages([
                        'nova_quantidade' => ['A nova quantidade deve ser diferente da atual.'],
                    ]);
                }

                $record->diferenca = round($newQuantity - $currentQuantity, 3);
            }

            $record->save();

            return $record;
        });

        return response()->json(
            $updated->load([
                'product:id,nome,codigo,unidade,estoque_atual,estoque_minimo,category_id',
                'product.category:id,nome',
                'requester:id,name',
                'resolver:id,name',
            ]),
        );
    }

    public function destroy(StockAdjustment $stockAdjustment): JsonResponse
    {
        if ($stockAdjustment->status !== StockAdjustment::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Somente ajustes pendentes podem ser removidos.'],
            ]);
        }

        $stockAdjustment->delete();

        return response()->json(['message' => 'Solicitação de ajuste removida.']);
    }

    private function applyStockAdjustment(StockAdjustment $adjustment, ?int $actorId): void
    {
        $product = Product::query()->lockForUpdate()->findOrFail($adjustment->product_id);

        $previousQuantity = round((float) $product->estoque_atual, 3);
        $targetQuantity = round((float) $adjustment->nova_quantidade, 3);

        if (abs($targetQuantity - $previousQuantity) < 0.000001) {
            throw ValidationException::withMessages([
                'nova_quantidade' => ['A nova quantidade deve ser diferente da atual para aprovar.'],
            ]);
        }

        $delta = round($targetQuantity - $previousQuantity, 3);

        $product->estoque_atual = $targetQuantity;
        $product->save();

        $adjustment->quantidade_atual = $previousQuantity;
        $adjustment->diferenca = $delta;
        $adjustment->status = StockAdjustment::STATUS_APPROVED;
        $adjustment->resolved_by = $actorId;
        $adjustment->resolved_at = now();

        StockMovement::query()->create([
            'product_id' => $product->id,
            'stock_adjustment_id' => $adjustment->id,
            'tipo' => 'ajuste',
            'origem' => 'ajuste_estoque',
            'referencia' => sprintf('Ajuste %s', strtoupper(substr((string) $adjustment->id, 0, 8))),
            'quantidade_anterior' => $previousQuantity,
            'quantidade_movimentada' => $delta,
            'quantidade_atual' => $targetQuantity,
            'descricao' => $adjustment->complemento,
            'happened_at' => now(),
            'created_by' => $actorId,
        ]);
    }
}
