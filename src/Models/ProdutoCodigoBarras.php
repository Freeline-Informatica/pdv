<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProdutoCodigoBarras extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'produto_codigo_barras';

    protected $fillable = [
        'produto_id',
        'produto_apresentacao_id',
        'codigo',
        'tipo_codigo',
        'principal',
        'informacoes_complementares',
        'ativo',
    ];

    protected $casts = [
        'principal' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
