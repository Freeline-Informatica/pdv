<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiscalConfig extends Model
{
    use HasFactory;
    use HasUuid;

    protected $table = 'fiscal_config';

    protected $fillable = [
        'ambiente', 'serie_nfe', 'serie_nfce', 'proximo_numero_nfe', 'proximo_numero_nfce',
        'csc', 'id_csc', 'emitir_nfce', 'emitir_nfe', 'impressao_automatica',
        'notagil_enabled', 'notagil_base_url', 'notagil_token', 'notagil_company_id', 'notagil_operation_code_nfce', 'notagil_nfce_synchronous', 'notagil_operation_code_nfe',
        'notagil_webhook_url', 'notagil_webhook_secret', 'notagil_webhook_tolerance_seconds',
        'notagil_webhook_id', 'notagil_webhook_status', 'notagil_webhook_last_synced_at', 'notagil_webhook_last_error',
        'logo_url', 'layout_cupom',
    ];

    protected $casts = [
        'emitir_nfce' => 'boolean',
        'emitir_nfe' => 'boolean',
        'impressao_automatica' => 'boolean',
        'notagil_enabled' => 'boolean',
        'notagil_nfce_synchronous' => 'boolean',
        'notagil_webhook_tolerance_seconds' => 'integer',
        'notagil_webhook_last_synced_at' => 'datetime',
        'layout_cupom' => 'array',
    ];
}
