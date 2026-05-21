<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosTerminal extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'grupo_empresarial_id',
        'estabelecimento_id',
        'nome',
        'identificador',
        'ativo',
        'pdv_layout_mode',
        'pdv_restaurant_mode',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
