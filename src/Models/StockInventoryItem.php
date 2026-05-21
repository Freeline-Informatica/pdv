<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockInventoryItem extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'stock_inventory_id',
        'product_id',
        'quantidade_sistema',
        'quantidade_contada',
        'diferenca',
        'saved_at',
    ];

    protected $casts = [
        'quantidade_sistema' => 'decimal:3',
        'quantidade_contada' => 'decimal:3',
        'diferenca' => 'decimal:3',
        'saved_at' => 'datetime',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(StockInventory::class, 'stock_inventory_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
