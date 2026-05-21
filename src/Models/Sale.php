<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_FINALIZED = 'finalizada';
    public const STATUS_CANCELED = 'cancelada';

    protected $fillable = [
        'grupo_empresarial_id',
        'estabelecimento_id',
        'numero',
        'status',
        'document_type',
        'cliente_nome',
        'total_bruto',
        'total_financeiro',
        'juros_total',
        'sold_at',
        'canceled_at',
        'cancellation_reason',
        'created_by',
        'canceled_by',
    ];

    protected $casts = [
        'total_bruto' => 'decimal:2',
        'total_financeiro' => 'decimal:2',
        'juros_total' => 'decimal:2',
        'sold_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'sale_id');
    }

    public function fiscalDocument(): HasOne
    {
        return $this->hasOne(SaleFiscalDocument::class, 'sale_id')->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'created_by');
    }

    public function canceler(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'canceled_by');
    }
}
