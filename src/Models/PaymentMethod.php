<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'nome', 'tipo', 'ativo', 'tef_habilitado', 'tef_provedor', 'tef_adquirente',
        'parcelas_max', 'parcela_minima', 'taxa_debito', 'taxa_credito_vista',
        'taxa_credito_parcelado', 'dias_recebimento', 'observacoes', 'ordem_pdv',
        'permite_troco', 'permite_parcelamento', 'permite_multiplos_pagamentos',
        'parcelas_min', 'sem_juros_ate', 'paf_intermediator_cnpj',
        'paf_intermediator_identifier',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'tef_habilitado' => 'boolean',
        'permite_troco' => 'boolean',
        'permite_parcelamento' => 'boolean',
        'permite_multiplos_pagamentos' => 'boolean',
        'parcela_minima' => 'decimal:2',
        'taxa_debito' => 'decimal:2',
        'taxa_credito_vista' => 'decimal:2',
        'taxa_credito_parcelado' => 'decimal:2',
    ];

    public function plans(): HasMany
    {
        return $this->hasMany(PaymentPlan::class, 'payment_method_id');
    }
}
