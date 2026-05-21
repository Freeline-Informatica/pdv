<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcquirerTefConfig extends Model
{
    use HasFactory;
    use HasUuid;

    protected $table = 'acquirer_tef_config';

    protected $fillable = [
        'acquirer_id', 'provedor', 'terminal_id', 'estabelecimento_id', 'ip_servidor',
        'porta_servidor', 'ativo', 'observacoes', 'terminal_ref_id', 'tipo_integracao',
        'diretorio_gerenciador', 'diretorio_envio', 'diretorio_retorno',
        'enviar_rede', 'enviar_cnc', 'v700',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'enviar_rede' => 'boolean',
        'enviar_cnc' => 'boolean',
        'v700' => 'boolean',
    ];

    public function acquirer(): BelongsTo
    {
        return $this->belongsTo(Acquirer::class, 'acquirer_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(AcquirerTerminal::class, 'terminal_ref_id');
    }
}
