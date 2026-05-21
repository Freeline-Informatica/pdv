<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalItemProfile extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'fiscal_item_profiles';

    protected $fillable = [
        'display_name',
        'description',
        'item_type',
        'ncm',
        'ncm_descricao',
        'cest',
        'origem_mercadoria',
        'servico_codigo',
        'cod_classe_tributo',
        'ipi_classe',
        'ipi_cod_enquadramento',
        'ipi_selo_cod',
        'cod_iat',
        'cod_ippt',
        'identity_hash',
        'active',
        'source_type',
        'source_reference',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
