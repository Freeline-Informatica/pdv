<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PafDavItem extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'dav_id',
        'item_number',
        'product_id',
        'catalog_product_id',
        'product_code',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'total',
        'tax_situation',
        'tax_rate',
        'canceled',
        'quantity_decimals',
        'unit_price_decimals',
        'included_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'total' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'canceled' => 'boolean',
        'quantity_decimals' => 'integer',
        'unit_price_decimals' => 'integer',
        'included_at' => 'datetime',
    ];

    public function dav(): BelongsTo
    {
        return $this->belongsTo(PafDav::class, 'dav_id');
    }
}
