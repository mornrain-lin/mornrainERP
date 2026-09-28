<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';
    public const TYPE_ADJUST = 'adjust';

    protected $fillable = [
        'product_id', 'type', 'quantity', 'balance_after', 'order_id', 'user_id', 'remark',
    ];

    protected $casts = [
        'quantity' => 'int',
        'balance_after' => 'int',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_IN => '入库',
            self::TYPE_OUT => '出库',
            default => '盘点',
        };
    }
}
