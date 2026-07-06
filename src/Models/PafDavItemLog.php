<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PafDavItemLog extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'dav_id',
        'dav_item_id',
        'change_type',
        'product_code',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'tax_situation',
        'tax_rate',
        'canceled',
        'quantity_decimals',
        'unit_price_decimals',
        'changed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'canceled' => 'boolean',
        'quantity_decimals' => 'integer',
        'unit_price_decimals' => 'integer',
        'changed_at' => 'datetime',
    ];

    public function dav(): BelongsTo
    {
        return $this->belongsTo(PafDav::class, 'dav_id');
    }
}
