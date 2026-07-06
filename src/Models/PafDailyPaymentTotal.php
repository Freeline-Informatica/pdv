<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PafDailyPaymentTotal extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'movement_date',
        'payment_method_name',
        'document_type_code',
        'customer_document',
        'non_tax_document_number',
        'amount',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'amount' => 'decimal:2',
    ];
}
