<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoAuditoria extends Model
{
    use HasFactory;
    use HasUuid;

    protected $table = 'produto_auditoria';

    protected $fillable = [
        'produto_id',
        'user_id',
        'entidade_tipo',
        'entidade_id',
        'evento',
        'alteracoes',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'alteracoes' => 'array',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'user_id');
    }
}
