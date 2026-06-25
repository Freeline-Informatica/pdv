<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'sale_id',
        'payment_method_id',
        'metodo_nome',
        'descricao',
        'acquirer_terminal_id',
        'acquirer_terminal_type',
        'installment_type',
        'installments',
        'card_brand',
        'authorization_number',
        'nsu',
        'tef_data',
        'valor',
        'acquirer_fee_rate',
        'acquirer_fee_amount',
        'expected_net_amount',
        'expected_receipt_days',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'installments' => 'integer',
        'tef_data' => 'array',
        'acquirer_fee_rate' => 'decimal:4',
        'acquirer_fee_amount' => 'decimal:2',
        'expected_net_amount' => 'decimal:2',
        'expected_receipt_days' => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
