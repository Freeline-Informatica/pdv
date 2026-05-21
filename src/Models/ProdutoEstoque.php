<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProdutoEstoque extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'produto_estoque';

    protected $fillable = [
        'produto_id',
        'quantidade',
        'quantidade_minima',
        'quantidade_maxima',
        'numero_lote',
        'reduzir_estoque',
        'quantidade_minima_vendavel',
        'quantidade_alerta',
    ];

    protected $casts = [
        'quantidade' => 'decimal:6',
        'quantidade_minima' => 'decimal:6',
        'quantidade_maxima' => 'decimal:6',
        'reduzir_estoque' => 'boolean',
        'quantidade_minima_vendavel' => 'decimal:6',
        'quantidade_alerta' => 'decimal:6',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
