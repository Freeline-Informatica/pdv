<?php

namespace Freeline\Pdv\Standalone;

use Freeline\Pdv\Contracts\StockMovementService;
use Freeline\Pdv\Models\Product;
use Freeline\Pdv\Models\Produto;
use Freeline\Pdv\Models\ProdutoEstoque;
use Freeline\Pdv\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StandaloneStockMovementService implements StockMovementService
{
    public function decrease(string|int $productId, float $quantity, array $context = []): void
    {
        $this->move($productId, -abs($quantity), $context);
    }

    public function increase(string|int $productId, float $quantity, array $context = []): void
    {
        $this->move($productId, abs($quantity), $context);
    }

    private function move(string|int $productId, float $quantity, array $context): void
    {
        $product = Product::query()->find($productId);

        if (! $product) {
            $this->moveCatalogProduct($productId, $quantity, $context);
            return;
        }

        $previous = (float) $product->estoque_atual;
        $current = $previous + $quantity;

        $product->forceFill(['estoque_atual' => $current])->save();

        StockMovement::query()->create([
            'product_id' => $product->id,
            'tipo' => $quantity < 0 ? 'saida' : 'entrada',
            'origem' => $context['origem'] ?? 'pdv',
            'referencia' => $context['referencia'] ?? null,
            'quantidade_anterior' => $previous,
            'quantidade_movimentada' => abs($quantity),
            'quantidade_atual' => $current,
            'descricao' => $context['descricao'] ?? null,
            'happened_at' => $context['happened_at'] ?? Carbon::now(),
            'created_by' => $context['user_id'] ?? null,
        ]);
    }

    private function moveCatalogProduct(string|int $productId, float $quantity, array $context): void
    {
        $product = Produto::query()->find($productId);

        if (! $product) {
            return;
        }

        $stock = ProdutoEstoque::query()->firstOrCreate([
            'produto_id' => $product->id,
        ], [
            'quantidade' => 0,
            'reduzir_estoque' => true,
        ]);

        $previous = (float) ($stock->quantidade ?? 0);
        $current = $previous + $quantity;

        $stock->forceFill(['quantidade' => $current])->save();

        DB::table('produto_movimentacao_estoque')->insert([
            'id' => (string) Str::uuid(),
            'produto_id' => $product->id,
            'produto_estoque_id' => $stock->id,
            'estabelecimento_id' => $product->estabelecimento_id,
            'user_id' => $context['user_id'] ?? null,
            'tipo_movimento' => $quantity < 0 ? 'venda' : 'entrada',
            'origem_tipo' => $context['origem'] ?? 'pdv',
            'origem_id' => $context['origem_id'] ?? null,
            'quantidade' => $quantity,
            'saldo_anterior' => $previous,
            'saldo_posterior' => $current,
            'observacao' => $context['descricao'] ?? null,
            'data_movimento' => $context['happened_at'] ?? Carbon::now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
