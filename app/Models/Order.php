<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\ProfitCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_no', 'shop_id', 'platform_id', 'buyer_name', 'buyer_country',
        'currency', 'exchange_rate',
        'goods_amount', 'shipping_income', 'discount_amount',
        'platform_commission', 'payment_fee', 'refund_amount',
        'shipping_cost', 'ad_cost', 'other_cost',
        'status', 'source', 'paid_at', 'shipped_at', 'completed_at', 'remark',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'exchange_rate' => 'float',
        'goods_amount' => 'float',
        'shipping_income' => 'float',
        'discount_amount' => 'float',
        'platform_commission' => 'float',
        'payment_fee' => 'float',
        'refund_amount' => 'float',
        'shipping_cost' => 'float',
        'ad_cost' => 'float',
        'other_cost' => 'float',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ---------------- relations ----------------

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    // ---------------- scopes ----------------

    public function scopeStatus(Builder $q, ?string $status): Builder
    {
        return $status ? $q->where('status', $status) : $q;
    }

    public function scopePlatform(Builder $q, $platformId): Builder
    {
        return $platformId ? $q->where('platform_id', $platformId) : $q;
    }

    public function scopeShop(Builder $q, $shopId): Builder
    {
        return $shopId ? $q->where('shop_id', $shopId) : $q;
    }

    public function scopeSearch(Builder $q, ?string $kw): Builder
    {
        if (! $kw) {
            return $q;
        }

        return $q->where(function (Builder $sub) use ($kw) {
            $sub->where('order_no', 'like', "%{$kw}%")
                ->orWhere('buyer_name', 'like', "%{$kw}%")
                ->orWhereHas('items', fn (Builder $i) => $i->where('sku', 'like', "%{$kw}%")
                    ->orWhere('product_name', 'like', "%{$kw}%"));
        });
    }

    public function scopeBetweenDates(Builder $q, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $q->whereDate('created_at', '<=', $to);
        }

        return $q;
    }

    // ---------------- helpers ----------------

    /** 明细成本合计（优先使用 withSum 聚合，避免 N+1） */
    public function resolveProductCost(): float
    {
        if (array_key_exists('product_cost', $this->attributes)) {
            return (float) $this->attributes['product_cost'];
        }

        if ($this->relationLoaded('items')) {
            return (float) $this->items->sum('line_cost');
        }

        return (float) $this->items()->sum('line_cost');
    }

    /** 完整利润明细（营收/成本/毛利/毛利率），供列表与详情页统一调用 */
    public function profit(): array
    {
        return ProfitCalculator::fromOrder($this, $this->resolveProductCost());
    }

    public function scopeWithAggregates(Builder $q): Builder
    {
        return $q->withSum('items as total_quantity', 'quantity')
            ->withSum('items as product_cost', 'line_cost');
    }
}
