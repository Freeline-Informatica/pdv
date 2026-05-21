<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Produto;
use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProdutoClassificacaoMercadologica extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'produto_classificacao_mercadologica';

    protected $fillable = [
        'parent_id',
        'codigo',
        'descricao',
        'descricao_reduzida',
        'parametros_observacoes',
        'nivel',
        'path',
        'ordem',
        'ativo',
    ];

    protected $casts = [
        'nivel' => 'integer',
        'ordem' => 'integer',
        'ativo' => 'boolean',
        'parametros_observacoes' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('ordem')->orderBy('descricao');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Produto::class, 'classificacao_mercadologica_id');
    }
}
