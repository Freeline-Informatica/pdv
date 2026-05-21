<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockMovementsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $legacyQuery = StockMovement::query()
            ->with([
                'product:id,nome,codigo,unidade,estoque_atual,estoque_minimo,category_id',
                'product.category:id,nome',
                'adjustment:id,tipo,status',
                'creator:id,name',
            ]);

        $catalogQuery = DB::table('produto_movimentacao_estoque as movement')
            ->leftJoin('produto as product', 'product.id', '=', 'movement.produto_id')
            ->leftJoin('produto_estoque as stock', 'stock.id', '=', 'movement.produto_estoque_id')
            ->leftJoin('unidade_medida as unit', 'unit.id', '=', 'product.unidade_medida_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'movement.user_id')
            ->select([
                'movement.id',
                'movement.produto_id',
                'movement.tipo_movimento',
                'movement.origem_tipo',
                'movement.origem_id',
                'movement.quantidade',
                'movement.saldo_anterior',
                'movement.saldo_posterior',
                'movement.observacao',
                'movement.data_movimento',
                'movement.created_at',
                'movement.user_id',
                'product.descricao as product_name',
                'product.cod_sku as product_sku',
                'product.codigo_operacional as product_code',
                'product.produto_familia_id as product_category_id',
                'stock.quantidade as product_stock',
                'stock.quantidade_minima as product_min_stock',
                'unit.unidade as product_unit',
                'creator.name as creator_name',
            ]);

        if ($request->filled('product_id')) {
            $productId = $request->string('product_id')->toString();
            $legacyQuery->where('product_id', $productId);
            $catalogQuery->where('movement.produto_id', $productId);
        }

        if ($request->filled('tipo')) {
            $type = $request->string('tipo')->toString();
            $legacyQuery->where('tipo', $type);
            $catalogQuery->where('movement.tipo_movimento', $type);
        }

        if ($request->filled('from')) {
            $from = $request->string('from')->toString();
            $legacyQuery->whereDate('happened_at', '>=', $from);
            $catalogQuery->whereDate('movement.data_movimento', '>=', $from);
        }

        if ($request->filled('to')) {
            $to = $request->string('to')->toString();
            $legacyQuery->whereDate('happened_at', '<=', $to);
            $catalogQuery->whereDate('movement.data_movimento', '<=', $to);
        }

        if ($request->filled('search')) {
            $needle = mb_strtolower($request->string('search')->toString());
            $legacyQuery->where(function ($builder) use ($needle): void {
                $builder->whereRaw('LOWER(COALESCE(referencia, \'\')) like ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(COALESCE(descricao, \'\')) like ?', ["%{$needle}%"])
                    ->orWhereHas('product', function ($productQuery) use ($needle): void {
                        $productQuery->whereRaw('LOWER(nome) like ?', ["%{$needle}%"])
                            ->orWhereRaw('LOWER(COALESCE(codigo, \'\')) like ?', ["%{$needle}%"]);
                    });
            });
            $catalogQuery->where(function ($builder) use ($needle): void {
                $builder->whereRaw('LOWER(COALESCE(movement.observacao, \'\')) like ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(COALESCE(movement.origem_tipo, \'\')) like ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(COALESCE(product.descricao, \'\')) like ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(COALESCE(product.cod_sku, \'\')) like ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(COALESCE(product.codigo_operacional, \'\')) like ?', ["%{$needle}%"]);
            });
        }

        $legacyMovements = $legacyQuery
            ->orderByDesc('happened_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (StockMovement $movement): array => $movement->toArray());

        $catalogMovements = $catalogQuery
            ->orderByDesc('movement.data_movimento')
            ->orderByDesc('movement.created_at')
            ->get()
            ->map(function ($movement): array {
                $productCode = trim((string) ($movement->product_sku ?: $movement->product_code ?: ''));
                $unit = strtoupper(trim((string) ($movement->product_unit ?: 'UN'))) ?: 'UN';
                $reference = match ((string) $movement->origem_tipo) {
                    'pdv_venda' => 'Venda',
                    'cancelamento_venda' => 'Cancelamento Venda',
                    default => $movement->origem_tipo ?: 'Movimentação',
                };

                return [
                    'id' => (string) $movement->id,
                    'product_id' => (string) $movement->produto_id,
                    'stock_adjustment_id' => null,
                    'tipo' => (string) $movement->tipo_movimento,
                    'origem' => (string) ($movement->origem_tipo ?: 'catalogo'),
                    'referencia' => trim($reference.($movement->origem_id ? ' #'.$movement->origem_id : '')),
                    'quantidade_anterior' => (float) ($movement->saldo_anterior ?? 0),
                    'quantidade_movimentada' => (float) ($movement->quantidade ?? 0),
                    'quantidade_atual' => (float) ($movement->saldo_posterior ?? 0),
                    'descricao' => (string) ($movement->observacao ?? ''),
                    'happened_at' => $movement->data_movimento,
                    'created_at' => $movement->created_at,
                    'created_by' => $movement->user_id,
                    'product' => [
                        'id' => (string) $movement->produto_id,
                        'nome' => (string) ($movement->product_name ?: 'Produto removido'),
                        'codigo' => $productCode,
                        'unidade' => $unit,
                        'estoque_atual' => (float) ($movement->product_stock ?? $movement->saldo_posterior ?? 0),
                        'estoque_minimo' => (float) ($movement->product_min_stock ?? 0),
                        'category_id' => $movement->product_category_id,
                    ],
                    'creator' => $movement->user_id ? [
                        'id' => $movement->user_id,
                        'name' => $movement->creator_name,
                    ] : null,
                ];
            });

        return response()->json(
            $legacyMovements
                ->concat($catalogMovements)
                ->sortByDesc(fn (array $movement): string => (string) ($movement['happened_at'] ?? $movement['created_at'] ?? ''))
                ->values(),
        );
    }
}
