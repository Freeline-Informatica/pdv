<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'produto_nome',
        'produto_codigo',
        'quantidade',
        'quantidade_recebida',
        'custo_unitario',
        'total',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'quantidade_recebida' => 'decimal:3',
        'custo_unitario' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

