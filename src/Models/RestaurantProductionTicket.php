<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantProductionTicket extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_NEW = 'novo';
    public const STATUS_PREPARING = 'em_preparo';
    public const STATUS_READY = 'pronto';
    public const STATUS_DELIVERED = 'entregue';

    protected $table = 'restaurant_production_tickets';

    protected $fillable = [
        'table_id',
        'ficha_id',
        'sector',
        'status',
        'waiter_name',
        'order_observation',
        'started_at',
        'ready_at',
        'delivered_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ready_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(RestaurantFicha::class, 'ficha_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RestaurantProductionTicketItem::class, 'ticket_id');
    }
}
