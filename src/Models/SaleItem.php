<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'sale_id',
        'product_id',
        'catalog_product_id',
        'produto_nome',
        'produto_codigo',
        'quantidade',
        'unidade',
        'valor_unitario',
        'valor_total',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'valor_unitario' => 'decimal:2',
        'valor_total' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.product', Product::class), 'product_id');
    }

    public function catalogProduct(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.produto', Produto::class), 'catalog_product_id');
    }
}
