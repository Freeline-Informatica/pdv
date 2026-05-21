<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'product_id',
        'stock_adjustment_id',
        'tipo',
        'origem',
        'referencia',
        'quantidade_anterior',
        'quantidade_movimentada',
        'quantidade_atual',
        'descricao',
        'happened_at',
        'created_by',
    ];

    protected $casts = [
        'quantidade_anterior' => 'decimal:3',
        'quantidade_movimentada' => 'decimal:3',
        'quantidade_atual' => 'decimal:3',
        'happened_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'created_by');
    }
}
