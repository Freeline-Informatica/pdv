<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PafPreSale extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_OPEN = 'aberta';
    public const STATUS_CONVERTED = 'convertida';

    protected $fillable = [
        'code',
        'status',
        'customer_name',
        'converted_sale_id',
        'converted_at',
        'created_by',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PafPreSaleItem::class, 'pre_sale_id')->orderBy('created_at');
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class, 'paf_pre_sale_id');
    }
}
