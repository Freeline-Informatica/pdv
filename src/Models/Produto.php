<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'produto';

    protected $fillable = [
        'estabelecimento_id',
        'produto_mestre_id',
        'fiscal_item_profile_id',
        'fiscal_item_profile_entrada_id',
        'fiscal_item_profile_saida_id',
        'classificacao_mercadologica_id',
        'unidade_medida_id',
        'produto_familia_id',
        'cod_sku',
        'codigo_operacional',
        'codigo_operacional_manual',
        'descricao',
        'descricao_curta',
        'produto_tipo',
        'situacao',
        'liberado',
        'marca',
        'palavra_chave',
        'permite_fracionamento',
        'atributos_logisticos',
    ];

    protected $casts = [
        'codigo_operacional_manual' => 'boolean',
        'permite_fracionamento' => 'boolean',
        'atributos_logisticos' => 'array',
    ];

    public function unidadeMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class, 'unidade_medida_id');
    }

    public function familia(): BelongsTo
    {
        return $this->belongsTo(ProdutoFamilia::class, 'produto_familia_id');
    }

    public function classificacaoMercadologica(): BelongsTo
    {
        return $this->belongsTo(ProdutoClassificacaoMercadologica::class, 'classificacao_mercadologica_id');
    }

    public function fiscalItemProfile(): BelongsTo
    {
        return $this->belongsTo(FiscalItemProfile::class, 'fiscal_item_profile_id');
    }

    public function fiscalItemProfileSaida(): BelongsTo
    {
        return $this->belongsTo(FiscalItemProfile::class, 'fiscal_item_profile_saida_id');
    }

    public function precos(): HasMany
    {
        return $this->hasMany(ProdutoPreco::class, 'produto_id')->orderByDesc('ativo')->orderBy('tipo');
    }

    public function codigosBarras(): HasMany
    {
        return $this->hasMany(ProdutoCodigoBarras::class, 'produto_id')->orderByDesc('principal');
    }

    public function estoque(): HasOne
    {
        return $this->hasOne(ProdutoEstoque::class, 'produto_id');
    }

    public function auditorias(): HasMany
    {
        return $this->hasMany(ProdutoAuditoria::class, 'produto_id')->latest();
    }
}
