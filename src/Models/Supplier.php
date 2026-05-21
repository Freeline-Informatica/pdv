<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'nome',
        'documento',
        'telefone',
        'email',
        'endereco',
        'observacoes',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}

