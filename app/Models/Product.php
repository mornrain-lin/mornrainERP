<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku', 'name', 'category', 'cost_price', 'weight_g', 'is_active',
        'stock', 'safety_stock',
    ];

    protected $casts = [
        'cost_price' => 'float',
        'weight_g' => 'float',
        'is_active' => 'bool',
        'stock' => 'int',
        'safety_stock' => 'int',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    /** 库存吃紧：已设安全库存且当前库存不高于安全线 */
    public function isLowStock(): bool
    {
        return $this->safety_stock > 0 && $this->stock <= $this->safety_stock;
    }
}
