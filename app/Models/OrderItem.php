<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_id', 'sku', 'product_name', 'quantity',
        'unit_price', 'unit_cost', 'line_total', 'line_cost',
    ];

    protected $casts = [
        'quantity' => 'int',
        'unit_price' => 'float',
        'unit_cost' => 'float',
        'line_total' => 'float',
        'line_cost' => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** 自动维护行小计，避免调用方遗漏 */
    protected static function booted(): void
    {
        static::saving(function (OrderItem $item) {
            $item->line_total = round($item->unit_price * $item->quantity, 2);
            $item->line_cost = round($item->unit_cost * $item->quantity, 2);
        });
    }
}
