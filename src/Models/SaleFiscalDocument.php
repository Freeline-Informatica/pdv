<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleFiscalDocument extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CONTINGENCY_PENDING = 'contingency_pending';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'sale_id',
        'document_type',
        'environment',
        'series',
        'number',
        'operation_code',
        'external_id',
        'idempotency_key',
        'status',
        'fiscal_status',
        'operational_status',
        'access_key',
        'protocol',
        'authorized_at',
        'last_error',
        'request_payload',
        'response_payload',
        'attempts',
        'last_attempt_at',
        'next_retry_at',
        'contingency_printed_at',
    ];

    protected $casts = [
        'number' => 'integer',
        'authorized_at' => 'datetime',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'attempts' => 'integer',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'contingency_printed_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
