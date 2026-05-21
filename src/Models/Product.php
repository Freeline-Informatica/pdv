<?php

namespace Freeline\Pdv\Models;

use Freeline\Pdv\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'nome', 'codigo', 'preco_venda', 'preco_custo', 'unidade',
        'estoque_atual', 'estoque_minimo', 'category_id', 'ativo', 'observacoes', 'imagem_url', 'restaurant_config',
    ];

    protected $casts = [
        'preco_venda' => 'decimal:2',
        'preco_custo' => 'decimal:2',
        'estoque_atual' => 'decimal:2',
        'estoque_minimo' => 'decimal:2',
        'ativo' => 'boolean',
        'restaurant_config' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class, 'product_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }

    public function stockInventoryItems(): HasMany
    {
        return $this->hasMany(StockInventoryItem::class, 'product_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'product_id');
    }
}
