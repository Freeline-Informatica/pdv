<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PafDav extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_OPEN = 'aberto';
    public const STATUS_CONVERTED = 'convertido';

    protected $table = 'paf_davs';

    protected $fillable = [
        'grupo_empresarial_id',
        'estabelecimento_id',
        'number',
        'title',
        'status',
        'customer_name',
        'customer_document',
        'external_requisition_id',
        'total',
        'issued_at',
        'converted_sale_id',
        'converted_at',
        'created_by',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'issued_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PafDavItem::class, 'dav_id')->orderBy('item_number');
    }

    public function itemLogs(): HasMany
    {
        return $this->hasMany(PafDavItemLog::class, 'dav_id')->orderBy('changed_at');
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class, 'paf_dav_id');
    }
}
