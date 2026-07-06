<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantScaleCustomerAccount extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'identifier',
        'ficha_id',
        'product_id',
        'weight',
        'unit_price',
        'total',
        'captured_at',
    ];

    protected $casts = [
        'weight' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'total' => 'decimal:2',
        'captured_at' => 'datetime',
    ];
}
