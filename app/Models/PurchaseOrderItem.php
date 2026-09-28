<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id', 'product_id', 'sku', 'product_name',
        'quantity', 'unit_cost', 'line_total', 'received_qty', 'remark',
    ];

    protected $casts = [
        'quantity' => 'int',
        'unit_cost' => 'float',
        'line_total' => 'float',
        'received_qty' => 'int',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** 自动维护行小计 */
    protected static function booted(): void
    {
        static::saving(function (PurchaseOrderItem $item) {
            $item->line_total = round((float) $item->unit_cost * (int) $item->quantity, 2);
        });
    }
}
