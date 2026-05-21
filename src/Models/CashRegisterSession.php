<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegisterSession extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_OPEN = 'aberto';
    public const STATUS_CLOSED = 'fechado';

    protected $fillable = [
        'grupo_empresarial_id',
        'estabelecimento_id',
        'terminal_nome',
        'terminal_codigo',
        'status',
        'opened_at',
        'closed_at',
        'opening_amount',
        'cash_received_amount',
        'sangria_amount',
        'suprimento_amount',
        'expected_amount',
        'counted_amount',
        'difference_amount',
        'observacoes',
        'opened_by',
        'closed_by',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_amount' => 'decimal:2',
        'cash_received_amount' => 'decimal:2',
        'sangria_amount' => 'decimal:2',
        'suprimento_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'counted_amount' => 'decimal:2',
        'difference_amount' => 'decimal:2',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(CashRegisterMovement::class, 'cash_register_session_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'closed_by');
    }
}
