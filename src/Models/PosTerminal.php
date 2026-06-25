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
        'printer_connection_mode',
        'printer_bridge_base_url',
        'printer_bridge_device_id',
        'scale_connection_mode',
        'scale_bridge_base_url',
        'scale_bridge_device_id',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
