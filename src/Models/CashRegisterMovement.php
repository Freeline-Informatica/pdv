<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegisterMovement extends Model
{
    use HasFactory;
    use HasUuid;

    public const TYPE_OPENING = 'abertura';
    public const TYPE_SUPPLY = 'suprimento';
    public const TYPE_WITHDRAWAL = 'sangria';
    public const TYPE_CLOSING = 'fechamento';

    protected $fillable = [
        'cash_register_session_id',
        'type',
        'amount',
        'description',
        'happened_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'happened_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'created_by');
    }
}
