<?php

namespace App\Services\Inventory;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 库存服务
 *
 * 约定：
 *  - 库存余额以 products.stock 为准，流水只解释「为什么变」
 *  - 发货即出库；退款完成自动回补
 *  - 允许负库存（超卖要被看见，而不是悄悄抹平），列表页会标红
 */
class StockService
{
    /**
     * 库存变动唯一入口
     *
     * @param  int  $quantity  正数入库，负数出库
     */
    public function adjust(
        Product $product,
        int $quantity,
        string $type,
        ?string $remark = null,
        ?int $orderId = null,
        ?int $userId = null,
    ): StockMovement {
        return DB::transaction(function () use ($product, $quantity, $type, $remark, $orderId, $userId) {
            $fresh = Product::whereKey($product->id)->first() ?? $product;
            $balance = (int) $fresh->stock + $quantity;

            $fresh->forceFill(['stock' => $balance])->save();

            return StockMovement::create([
                'product_id' => $fresh->id,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $balance,
                'order_id' => $orderId,
                'user_id' => $userId,
                'remark' => $remark,
            ]);
        });
    }

    /** 发货出库：按订单明细逐行扣减，未匹配到商品的 SKU 会被跳过 */
    public function deductForOrder(Order $order): int
    {
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();
        if ($items->isEmpty()) {
            return 0;
        }

        $skus = $items->pluck('sku')->filter()->unique()->all();
        $products = Product::whereIn('sku', $skus)->get()->keyBy('sku');

        $moved = 0;
        foreach ($items as $item) {
            $product = $products->get($item->sku);
            if (! $product) {
                continue;
            }
            $this->adjust(
                $product,
                -1 * (int) $item->quantity,
                StockMovement::TYPE_OUT,
                "订单 {$order->order_no} 发货出库",
                $order->id,
                auth()->id(),
            );
            $moved++;
        }

        return $moved;
    }

    /** 退款完成回补库存 */
    public function returnForOrder(Order $order): int
    {
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();
        $skus = $items->pluck('sku')->filter()->unique()->all();
        $products = Product::whereIn('sku', $skus)->get()->keyBy('sku');

        $moved = 0;
        foreach ($items as $item) {
            $product = $products->get($item->sku);
            if (! $product) {
                continue;
            }
            $this->adjust(
                $product,
                (int) $item->quantity,
                StockMovement::TYPE_IN,
                "订单 {$order->order_no} 退款回补",
                $order->id,
                auth()->id(),
            );
            $moved++;
        }

        return $moved;
    }

    /** 低于安全库存的商品（只统计设置了安全库存的 SKU） */
    public function lowStockProducts(): Collection
    {
        return Product::where('is_active', true)
            ->where('safety_stock', '>', 0)
            ->whereColumn('stock', '<=', 'safety_stock')
            ->orderBy('stock')
            ->get();
    }

    /**
     * 采购建议：按近 N 天销量 + 安全库存测算补货量
     *
     * @return Collection<int, array{product: Product, sold: int, suggest: int, amount: float}>
     */
    public function purchaseSuggestions(int $days = 30): Collection
    {
        $validStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Shipped->value,
            OrderStatus::Completed->value,
        ];

        $sold = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', now()->subDays($days))
            ->whereIn('orders.status', $validStatuses)
            ->groupBy('order_items.sku')
            ->selectRaw('order_items.sku as sku, SUM(order_items.quantity) as qty')
            ->pluck('qty', 'sku');

        return Product::where('is_active', true)
            ->orderBy('sku')
            ->get()
            ->map(function (Product $p) use ($sold) {
                $sold30 = (int) ($sold[$p->sku] ?? 0);
                $suggest = max(0, (int) $p->safety_stock + $sold30 - (int) $p->stock);

                return [
                    'product' => $p,
                    'sold' => $sold30,
                    'suggest' => $suggest,
                    'amount' => round($suggest * (float) $p->cost_price, 2),
                ];
            })
            ->filter(fn (array $row) => $row['suggest'] > 0)
            ->sortByDesc('suggest')
            ->values();
    }
}
