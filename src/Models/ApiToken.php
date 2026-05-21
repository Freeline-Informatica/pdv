<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiToken extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'user_id',
        'grupo_empresarial_id',
        'estabelecimento_id',
        'token_hash',
        'settings_access_hash',
        'settings_access_expires_at',
        'settings_access_granted_by_user_id',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'settings_access_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('pdv.models.user', User::class));
    }
}
