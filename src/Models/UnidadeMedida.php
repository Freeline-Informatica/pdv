<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnidadeMedida extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'unidade_medida';

    protected $fillable = [
        'estabelecimento_id',
        'unidade',
        'descricao',
        'descricao_plural',
        'decimais',
        'artigo',
        'codigo_fiscal',
        'status',
    ];

    protected $casts = [
        'decimais' => 'integer',
        'status' => 'boolean',
    ];
}
