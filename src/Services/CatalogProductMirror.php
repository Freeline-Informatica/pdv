<?php

namespace Freeline\Pdv\Services;

use Freeline\Pdv\Models\Product;
use Freeline\Pdv\Models\Produto;
use Illuminate\Support\Collection;

class CatalogProductMirror
{
    public function sync(?string $search = null, int $limit = 100): Collection
    {
        $needle = mb_strtolower(trim((string) $search));

        $catalogProducts = Produto::query()
            ->with([
                'estoque:id,produto_id,quantidade,quantidade_minima',
                'unidadeMedida:id,unidade',
                'codigosBarras:id,produto_id,codigo,principal',
                'precos:id,produto_id,tipo,canal,valor,custo_referencial,ativo',
            ])
            ->where(function ($builder): void {
                $builder->whereNull('situacao')
                    ->orWhereRaw('LOWER(situacao) = ?', ['ativo']);
            })
            ->where(function ($builder): void {
                $builder->whereNull('liberado')
                    ->orWhereRaw('LOWER(liberado) = ?', ['sim']);
            })
            ->when($needle !== '', function ($query) use ($needle): void {
                $query->where(function ($builder) use ($needle): void {
                    $builder->whereRaw('LOWER(descricao) LIKE ?', ["%{$needle}%"])
                        ->orWhereRaw("LOWER(COALESCE(descricao_curta, '')) LIKE ?", ["%{$needle}%"])
                        ->orWhereRaw("LOWER(COALESCE(cod_sku, '')) LIKE ?", ["%{$needle}%"])
                        ->orWhereRaw("LOWER(COALESCE(codigo_operacional, '')) LIKE ?", ["%{$needle}%"])
                        ->orWhereHas('codigosBarras', function ($barcodeQuery) use ($needle): void {
                            $barcodeQuery->whereRaw('LOWER(codigo) LIKE ?', ["%{$needle}%"]);
                        });
                });
            })
            ->orderBy('descricao')
            ->limit(max(1, min($limit, 100)))
            ->get();

        $this->mirror($catalogProducts);

        return $catalogProducts;
    }

    public function syncProduct(string $productId): void
    {
        $catalogProducts = Produto::query()
            ->with([
                'estoque:id,produto_id,quantidade,quantidade_minima',
                'unidadeMedida:id,unidade',
                'codigosBarras:id,produto_id,codigo,principal',
                'precos:id,produto_id,tipo,canal,valor,custo_referencial,ativo',
            ])
            ->whereKey($productId)
            ->get();

        $this->mirror($catalogProducts);
    }

    private function mirror(Collection $catalogProducts): void
    {
        if ($catalogProducts->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $catalogProducts->map(function (Produto $produto) use ($now): array {
            $barcode = $produto->codigosBarras->firstWhere('principal', true)
                ?? $produto->codigosBarras->first();
            $price = $produto->precos->firstWhere('ativo', true)
                ?? $produto->precos->first();
            $attributes = is_array($produto->atributos_logisticos)
                ? $produto->atributos_logisticos
                : [];
            $rawNcm = (string) data_get($attributes, 'fiscal_ncm', data_get($attributes, 'importacao_simples.ncm', ''));
            $ncm = preg_replace('/\D+/', '', $rawNcm);
            $cest = (string) data_get($attributes, 'fiscal_cest', data_get($attributes, 'importacao_simples.cest', ''));
            $cost = $price?->custo_referencial ?? data_get($attributes, 'importacao_simples.custo', 0);
            $restaurantConfig = $attributes;
            $restaurantConfig['catalog_product_id'] = (string) $produto->id;
            $restaurantConfig['tributacao'] = array_filter([
                'ncm' => $ncm !== '' ? $ncm : null,
                'cest' => $cest !== '' ? $cest : null,
                'fiscal_item_profile_id' => $produto->fiscal_item_profile_id,
                'fiscal_item_profile_entrada_id' => $produto->fiscal_item_profile_entrada_id,
                'fiscal_item_profile_saida_id' => $produto->fiscal_item_profile_saida_id,
                'unidade_tributavel' => mb_strtoupper((string) ($produto->unidadeMedida?->codigo_fiscal ?: $produto->unidadeMedida?->unidade ?: 'UN')),
            ], static fn ($value) => $value !== null && $value !== '');

            return [
                'id' => (string) $produto->id,
                'nome' => (string) ($produto->descricao ?: $produto->descricao_curta ?: 'Produto sem descrição'),
                'codigo' => (string) ($produto->cod_sku ?: $produto->codigo_operacional ?: ($barcode?->codigo ?: '')) ?: null,
                'preco_venda' => round((float) ($price?->valor ?? 0), 2),
                'preco_custo' => round((float) ($cost ?? 0), 2),
                'unidade' => (string) ($produto->unidadeMedida?->unidade ?: 'UN'),
                'estoque_atual' => round((float) ($produto->estoque?->quantidade ?? 0), 3),
                'estoque_minimo' => round((float) ($produto->estoque?->quantidade_minima ?? 0), 3),
                'category_id' => null,
                'ativo' => $this->isActive($produto),
                'observacoes' => $produto->descricao_curta,
                'imagem_url' => data_get($attributes, 'informacao_adicional.fotos.0.url'),
                'restaurant_config' => json_encode($restaurantConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        Product::query()->upsert(
            $rows->all(),
            ['id'],
            [
                'nome',
                'codigo',
                'preco_venda',
                'preco_custo',
                'unidade',
                'estoque_atual',
                'estoque_minimo',
                'ativo',
                'observacoes',
                'imagem_url',
                'restaurant_config',
                'updated_at',
            ],
        );
    }

    private function isActive(Produto $produto): bool
    {
        $situacao = mb_strtolower((string) $produto->situacao);
        $liberado = mb_strtolower((string) $produto->liberado);

        return ($situacao === '' || $situacao === 'ativo')
            && ($liberado === '' || $liberado === 'sim');
    }
}
