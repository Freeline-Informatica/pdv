<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DigitalCertificate extends Model
{
    use HasFactory;
    use HasUuid;

    protected $table = 'digital_certificate';

    protected $fillable = [
        'tipo', 'validade', 'arquivo_nome', 'senha_hash',
        'pfx_storage_path', 'pfx_password_encrypted', 'pfx_uploaded_at',
    ];

    protected $casts = [
        'validade' => 'date',
        'pfx_uploaded_at' => 'datetime',
    ];
}
