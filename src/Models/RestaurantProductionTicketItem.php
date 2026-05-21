<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantProductionTicketItem extends Model
{
    use HasFactory;
    use HasUuid;

    protected $table = 'restaurant_production_ticket_items';

    protected $fillable = [
        'ticket_id',
        'product_id',
        'product_name',
        'product_code',
        'quantity',
        'unit_price',
        'total_price',
        'observation',
        'selected_options',
        'removed_ingredients',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RestaurantProductionTicket::class, 'ticket_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
