<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PafExternalRequisition extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_RECEIVED = 'R';
    public const STATUS_ATTENDED = 'A';
    public const STATUS_DENIED = 'D';

    protected $fillable = [
        'cre',
        'origin',
        'status',
        'external_order_id',
        'requester_cnpj',
        'dav_id',
        'pre_sale_id',
        'restaurant_ficha_id',
        'total',
        'raw_payload',
        'attended_sale_id',
        'attended_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'raw_payload' => 'array',
        'attended_at' => 'datetime',
    ];

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class, 'paf_external_requisition_id');
    }
}
