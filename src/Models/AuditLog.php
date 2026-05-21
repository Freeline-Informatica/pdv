<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'grupo_empresarial_id',
        'estabelecimento_id',
        'action_key',
        'action_label',
        'entity',
        'entity_id',
        'operator_id',
        'operator_name',
        'operator_code',
        'operator_role',
        'details',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class), 'operator_id');
    }
}
