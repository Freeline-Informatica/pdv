<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PafPreSaleItem extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'pre_sale_id',
        'product_id',
        'catalog_product_id',
        'product_code',
        'description',
        'quantity',
        'unit',
        'included_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'included_at' => 'datetime',
    ];

    public function preSale(): BelongsTo
    {
        return $this->belongsTo(PafPreSale::class, 'pre_sale_id');
    }
}
