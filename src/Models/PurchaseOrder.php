<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_OPEN = 'aberto';
    public const STATUS_RECEIVED = 'recebido';

    protected $fillable = [
        'numero',
        'supplier_id',
        'data_compra',
        'filial',
        'status',
        'observacoes',
        'total_items',
        'total_quantity',
        'total_value',
        'received_at',
        'created_by',
        'received_by',
    ];

    protected $casts = [
        'data_compra' => 'date',
        'total_quantity' => 'decimal:3',
        'total_value' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'created_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'received_by');
    }
}
