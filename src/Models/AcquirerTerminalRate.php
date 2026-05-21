<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcquirerTerminalRate extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'terminal_id', 'tipo_credito', 'taxa_operadora', 'recebe_em',
        'parc_sugerida', 'parc_maximo', 'ativo', 'parc_inicial', 'parc_final',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'taxa_operadora' => 'decimal:2',
    ];

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(AcquirerTerminal::class, 'terminal_id');
    }
}
