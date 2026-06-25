<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'tipo_pessoa',
        'cpf_cnpj',
        'nome',
        'nome_fantasia',
        'telefone',
        'email',
        'cep',
        'logradouro',
        'numero',
        'bairro',
        'complemento',
        'cidade',
        'uf',
        'pais',
        'inscricao_estadual',
        'observacoes',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
