<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantTableTransfer extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'from_ficha_id',
        'to_ficha_id',
        'from_table_code',
        'to_table_code',
        'ticket_item_id',
        'product_code',
        'product_name',
        'quantity',
        'unit_price',
        'transferred_at',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'transferred_at' => 'datetime',
    ];
}
