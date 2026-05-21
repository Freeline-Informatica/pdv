<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcquirerTerminal extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'acquirer_id', 'tipo', 'estacao', 'descricao', 'formula',
    ];

    public function acquirer(): BelongsTo
    {
        return $this->belongsTo(Acquirer::class, 'acquirer_id');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(AcquirerTerminalRate::class, 'terminal_id');
    }
}
