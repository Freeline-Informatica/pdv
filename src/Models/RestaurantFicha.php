<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantFicha extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_OPENED = 'opened';
    public const STATUS_IN_SERVICE = 'em_atendimento';
    public const STATUS_WAITING_PRODUCTION = 'aguardando_producao';
    public const STATUS_PARTIALLY_DELIVERED = 'parcialmente_entregue';
    public const STATUS_IN_CONFERENCE = 'em_conferencia';
    public const STATUS_WAITING_PAYMENT = 'aguardando_pagamento';
    public const STATUS_PAID = 'paga';
    public const STATUS_CANCELED = 'cancelada';
    public const STATUS_CLOSED = 'closed';

    protected $table = 'restaurant_fichas';

    protected $fillable = [
        'table_id',
        'code',
        'customer_name',
        'is_random_customer',
        'waiter_name',
        'observation',
        'status',
        'opened_at',
        'closed_at',
        'closing_requested_at',
        'closing_requested_by',
    ];

    protected $casts = [
        'is_random_customer' => 'boolean',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'closing_requested_at' => 'datetime',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function productionTickets(): HasMany
    {
        return $this->hasMany(RestaurantProductionTicket::class, 'ficha_id');
    }
}
