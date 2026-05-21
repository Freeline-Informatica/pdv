<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProdutoPreco extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'produto_preco';

    protected $fillable = [
        'produto_id',
        'tipo',
        'codigo',
        'canal',
        'valor',
        'percentual',
        'custo_referencial',
        'margem',
        'margem_preco_minimo',
        'vigencia_inicio',
        'vigencia_fim',
        'ativo',
    ];

    protected $casts = [
        'valor' => 'decimal:4',
        'percentual' => 'decimal:2',
        'custo_referencial' => 'decimal:4',
        'margem' => 'decimal:2',
        'margem_preco_minimo' => 'decimal:2',
        'vigencia_inicio' => 'date',
        'vigencia_fim' => 'date',
        'ativo' => 'boolean',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
