<?php

namespace Freeline\Pdv\Standalone;

use Freeline\Pdv\Contracts\ProductCatalogRepository;
use Freeline\Pdv\Models\Produto;
use Freeline\Pdv\Models\ProdutoFamilia;
use Illuminate\Support\Collection;

class StandaloneProductCatalogRepository implements ProductCatalogRepository
{
    public function categories(): Collection
    {
        return ProdutoFamilia::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->values();
    }

    public function search(array $filters = []): Collection
    {
        return Produto::query()
            ->with([
                'precos:id,produto_id,valor,ativo',
                'estoque:id,produto_id,quantidade',
                'codigosBarras:id,produto_id,codigo,principal,ativo',
                'classificacaoMercadologica:id,parametros_observacoes',
                'fiscalTags:id,produto_id,tag',
                'fiscalItemProfile:id,display_name,ncm,cest,origem_mercadoria,cod_classe_tributo',
                'fiscalItemProfileSaida:id,display_name,ncm,cest,origem_mercadoria,cod_classe_tributo',
                'unidadeMedida:id,unidade,codigo_fiscal',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('descricao', 'like', "%{$search}%")
                        ->orWhere('descricao_curta', 'like', "%{$search}%")
                        ->orWhere('cod_sku', 'like', "%{$search}%")
                        ->orWhere('codigo_operacional', 'like', "%{$search}%");
                });
            })
            ->when($filters['category_id'] ?? null, fn ($query, mixed $categoryId) => $query->where('produto_familia_id', $categoryId))
            ->orderBy('descricao')
            ->limit((int) ($filters['limit'] ?? 500))
            ->get()
            ->map(fn (Produto $produto): array => $this->mapProduto($produto));
    }

    public function find(string|int $id): ?array
    {
        $produto = Produto::query()
            ->with([
                'precos:id,produto_id,valor,ativo',
                'estoque:id,produto_id,quantidade',
                'codigosBarras:id,produto_id,codigo,principal,ativo',
                'classificacaoMercadologica:id,parametros_observacoes',
                'fiscalTags:id,produto_id,tag',
                'fiscalItemProfile:id,display_name,ncm,cest,origem_mercadoria,cod_classe_tributo',
                'fiscalItemProfileSaida:id,display_name,ncm,cest,origem_mercadoria,cod_classe_tributo',
                'unidadeMedida:id,unidade,codigo_fiscal',
            ])
            ->find($id);

        return $produto ? $this->mapProduto($produto) : null;
    }

    private function mapProduto(Produto $produto): array
    {
        $mainBarcode = $produto->codigosBarras->firstWhere('principal', true)
            ?? $produto->codigosBarras->first();

        $activePrice = $produto->precos->firstWhere('ativo', true)
            ?? $produto->precos->first();
        $fiscalProfile = $produto->fiscalItemProfileSaida ?: $produto->fiscalItemProfile;
        $attributes = is_array($produto->atributos_logisticos) ? $produto->atributos_logisticos : [];
        $isService = $this->isServiceProduct($produto);

        return [
            'id' => $produto->id,
            'nome' => $produto->descricao ?: ($produto->descricao_curta ?: 'Produto sem descrição'),
            'preco_venda' => (float) ($activePrice?->valor ?? 0),
            'category_id' => $produto->produto_familia_id,
            'codigo' => $produto->cod_sku ?: $produto->codigo_operacional ?: ($mainBarcode?->codigo ?: null),
            'codigo_barras' => $mainBarcode?->codigo ?: null,
            'ean' => $mainBarcode?->codigo ?: null,
            'gtin' => $mainBarcode?->codigo ?: null,
            'imagem_url' => $this->resolveImageUrl($produto),
            'estoque_atual' => (float) ($produto->estoque?->quantidade ?? 0),
            'observacoes' => $produto->descricao_curta,
            'unidade' => strtoupper((string) ($produto->unidadeMedida?->unidade ?: 'UN')),
            'permite_fracionamento' => (bool) $produto->permite_fracionamento,
            'produto_pesavel' => $this->isWeighableProduct($produto),
            'produto_tipo' => $produto->produto_tipo,
            'tipo_item' => $produto->tipo_item,
            'natureza_item' => $produto->natureza_item,
            'ncm' => $produto->ncm,
            'cest' => $produto->cest,
            'origem_mercadoria' => $produto->origem_mercadoria,
            'servico_codigo' => $produto->servico_codigo,
            'codigo_nbs' => $produto->codigo_nbs,
            'fiscal_tags' => $produto->fiscalTags->pluck('tag')->values()->all(),
            'restaurant_config' => is_array($produto->atributos_logisticos) ? $produto->atributos_logisticos : null,
            'tributacao' => [
                'document_model' => $isService ? 'NFS-e' : 'NFC-e',
                'item_type' => $isService ? 'SERVICE' : 'PRODUCT',
                'profile' => $fiscalProfile?->display_name,
                'tipo_item' => $isService ? '09' : ($produto->tipo_item ?: '00'),
                'natureza_item' => $isService ? 'SERVICO' : ($produto->natureza_item ?: 'MERCADORIA'),
                'ncm' => $isService ? null : ($produto->ncm ?: $fiscalProfile?->ncm ?: data_get($attributes, 'fiscal_ncm')),
                'cest' => $isService ? null : ($produto->cest ?: $fiscalProfile?->cest ?: data_get($attributes, 'fiscal_cest')),
                'origem' => $isService ? null : ($produto->origem_mercadoria ?? $fiscalProfile?->origem_mercadoria ?? data_get($attributes, 'fiscal_origem', '0')),
                'servico_codigo' => $isService ? ($produto->servico_codigo ?: $fiscalProfile?->servico_codigo) : null,
                'codigo_nbs' => $isService ? $produto->codigo_nbs : null,
                'fiscal_tags' => $produto->fiscalTags->pluck('tag')->values()->all(),
                'tax_classification_code' => $produto->cod_classe_tributo ?: $fiscalProfile?->cod_classe_tributo ?: data_get($attributes, 'fiscal_tax_classification_code'),
                'unidade_tributavel' => strtoupper((string) ($produto->unidadeMedida?->codigo_fiscal ?: $produto->unidadeMedida?->unidade ?: 'UN')),
            ],
            'classificacao_mercadologica_id' => $produto->classificacao_mercadologica_id,
            'classification_observation_parameters' => array_values(
                is_array($produto->classificacaoMercadologica?->parametros_observacoes)
                    ? $produto->classificacaoMercadologica->parametros_observacoes
                    : [],
            ),
        ];
    }

    private function isWeighableProduct(Produto $produto): bool
    {
        foreach ([$produto->unidadeMedida?->codigo_fiscal, $produto->unidadeMedida?->unidade] as $candidate) {
            if (mb_strtoupper(trim((string) $candidate)) === 'KG') {
                return true;
            }
        }

        return false;
    }

    private function isServiceProduct(Produto $produto): bool
    {
        return mb_strtoupper(trim((string) $produto->produto_tipo)) === 'SERVICO'
            || trim((string) $produto->tipo_item) === '09'
            || mb_strtoupper(trim((string) $produto->natureza_item)) === 'SERVICO';
    }

    private function resolveImageUrl(Produto $produto): ?string
    {
        $attributes = is_array($produto->atributos_logisticos) ? $produto->atributos_logisticos : [];
        $photos = data_get($attributes, 'informacao_adicional.fotos', []);

        foreach (is_array($photos) ? $photos : [] as $photo) {
            $url = trim((string) data_get($photo, 'url', ''));
            if ($url !== '') {
                return $url;
            }
        }

        return null;
    }
}
