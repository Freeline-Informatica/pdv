<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'cnpj', 'razao_social', 'nome_fantasia', 'inscricao_estadual',
        'inscricao_municipal', 'regime_tributario', 'cnae', 'telefone',
        'email', 'cep', 'logradouro', 'numero', 'complemento', 'bairro',
        'cidade', 'uf', 'pdv_layout_mode', 'restaurant_parameters',
    ];

    protected $casts = [
        'restaurant_parameters' => 'array',
    ];
}
