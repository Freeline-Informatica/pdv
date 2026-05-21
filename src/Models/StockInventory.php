<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockInventory extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_OPEN = 'aberto';
    public const STATUS_IN_PROGRESS = 'em_andamento';
    public const STATUS_FINALIZED = 'finalizado';

    protected $fillable = [
        'status',
        'observacoes',
        'created_by',
        'submitted_by',
        'submitted_at',
        'submitted_adjustments_count',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockInventoryItem::class, 'stock_inventory_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'submitted_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class, 'stock_inventory_id');
    }
}
