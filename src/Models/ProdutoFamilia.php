<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProdutoFamilia extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'produto_familia';

    protected $fillable = [
        'grupo_empresarial_id',
        'codigo',
        'nome',
        'descricao',
        'codigo_prefixo',
        'modo_geracao_codigo',
        'faixa_inicial',
        'faixa_final',
        'proximo_numero',
        'ativo',
    ];

    protected $casts = [
        'faixa_inicial' => 'integer',
        'faixa_final' => 'integer',
        'proximo_numero' => 'integer',
        'ativo' => 'boolean',
    ];
}
