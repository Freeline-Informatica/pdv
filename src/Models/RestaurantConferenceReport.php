<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantConferenceReport extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'ficha_id',
        'number',
        'total',
        'generated_at',
        'generated_by',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'generated_at' => 'datetime',
    ];
}
