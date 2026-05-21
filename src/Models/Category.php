<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'nome', 'descricao', 'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
