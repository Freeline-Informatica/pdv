<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentPlan extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'nome', 'payment_method_id', 'ativo', 'ordem_pdv', 'quantidade_parcelas',
        'intervalo_parcelas', 'valor_minimo_parcela', 'possui_juros', 'percentual_juros', 'exibir_pdv',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'possui_juros' => 'boolean',
        'exibir_pdv' => 'boolean',
        'valor_minimo_parcela' => 'decimal:2',
        'percentual_juros' => 'decimal:2',
    ];

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
