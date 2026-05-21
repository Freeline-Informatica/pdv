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
        'notagil_enabled', 'notagil_company_id', 'notagil_operation_code_nfce', 'notagil_operation_code_nfe',
    ];

    protected $casts = [
        'emitir_nfce' => 'boolean',
        'emitir_nfe' => 'boolean',
        'impressao_automatica' => 'boolean',
        'notagil_enabled' => 'boolean',
    ];
}
