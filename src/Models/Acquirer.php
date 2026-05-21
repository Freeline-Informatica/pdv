<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Acquirer extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'nome', 'cnpj', 'ativo', 'observacoes',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function terminals(): HasMany
    {
        return $this->hasMany(AcquirerTerminal::class, 'acquirer_id');
    }
}
