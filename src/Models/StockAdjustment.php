<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_PENDING = 'pendente';
    public const STATUS_APPROVED = 'aprovado';
    public const STATUS_REJECTED = 'rejeitado';
    public const STATUS_CANCELED = 'cancelado';

    protected $fillable = [
        'product_id',
        'stock_inventory_id',
        'tipo',
        'status',
        'quantidade_atual',
        'nova_quantidade',
        'diferenca',
        'complemento',
        'requested_by',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'quantidade_atual' => 'decimal:3',
        'nova_quantidade' => 'decimal:3',
        'diferenca' => 'decimal:3',
        'resolved_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(StockInventory::class, 'stock_inventory_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'requested_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'resolved_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'stock_adjustment_id');
    }
}
