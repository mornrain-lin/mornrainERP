<?php

namespace App\Services\PlatformSync;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SyncLog;
use App\Services\Quota\QuotaService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * 订单同步服务
 *
 * 职责边界：
 *  - 只负责「平台订单 → 本地订单」的翻译与落库
 *  - 利润一律交给 ProfitCalculator，不在这里另算
 *  - 成本快照：明细行的 unit_cost 落单时从商品库取，历史调价不影响旧订单
 */
class OrderSyncService
{
    public function __construct(
        private readonly PlatformConnectorFactory $factory,
        private readonly QuotaService $quota,
    ) {
    }

    /**
     * 同步单个店铺
     *
     * @return array{status: string, message: ?string, fetched: int, created: int, updated: int, skipped: int}
     */
    public function syncShop(Shop $shop, CarbonInterface $from, CarbonInterface $to, string $trigger = 'manual'): array
    {
        $startedAt = microtime(true);
        $stats = ['fetched' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];
        $status = 'success';
        $message = null;

        try {
            $orders = $this->factory->make($shop)->fetchOrders($shop, $from, $to);
            $stats['fetched'] = count($orders);

            $remaining = $this->quota->remaining(); // null = 不限量
            $blocked = 0;

            foreach ($orders as $payload) {
                if (! $payload->isValid()) {
                    $stats['skipped']++;
                    continue;
                }

                $isNew = ! Order::where('shop_id', $shop->id)
                    ->where('order_no', $payload->orderNo)
                    ->exists();

                // 免费版额度：超过当月余额的新订单跳过（已有订单仍正常更新）
                if ($isNew && $remaining !== null && $remaining <= 0) {
                    $stats['skipped']++;
                    $blocked++;
                    continue;
                }

                $result = $this->upsertOrder($shop, $payload);
                $stats[$result] = ($stats[$result] ?? 0) + 1;
                if ($isNew && $remaining !== null) {
                    $remaining--;
                }
            }

            $message = sprintf(
                '拉取 %d 单，新建 %d，更新 %d，跳过 %d',
                $stats['fetched'], $stats['created'], $stats['updated'], $stats['skipped']
            );

            if ($blocked > 0) {
                $message .= "；{$blocked} 单因月度额度限制未新建";
            }

            $shop->forceFill([
                'last_synced_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_message' => $message,
            ])->save();
        } catch (ConnectorException $e) {
            $status = 'failed';
            $message = $e->getMessage();

            $shop->forceFill([
                'last_sync_status' => 'failed',
                'last_sync_message' => $message,
            ])->save();
        }

        SyncLog::create([
            'shop_id' => $shop->id,
            'trigger' => $trigger,
            'status' => $status,
            'range_from' => $from,
            'range_to' => $to,
            'fetched' => $stats['fetched'],
            'created' => $stats['created'],
            'updated' => $stats['updated'],
            'skipped' => $stats['skipped'],
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'message' => $message,
        ]);

        return ['status' => $status, 'message' => $message] + $stats;
    }

    /** @return array<int, array{shop: Shop, result: array}> */
    public function syncAll(string $trigger = 'manual'): array
    {
        $shops = Shop::where('is_active', true)->where('sync_enabled', true)->get();
        $results = [];

        foreach ($shops as $shop) {
            $from = $shop->last_synced_at ? $shop->last_synced_at->copy()->subHours(2) : now()->subDays(7);
            $results[] = [
                'shop' => $shop,
                'result' => $this->syncShop($shop, $from, now(), $trigger),
            ];
        }

        return $results;
    }

    /** @return 'created'|'updated'|'skipped' */
    private function upsertOrder(Shop $shop, NormalizedOrder $payload): string
    {
        if (! $payload->isValid()) {
            return 'skipped';
        }

        return DB::transaction(function () use ($shop, $payload) {
            $order = Order::firstOrNew([
                'shop_id' => $shop->id,
                'order_no' => $payload->orderNo,
            ]);

            $isNew = ! $order->exists;

            $data = [
                'platform_id' => $shop->platform_id,
                'buyer_name' => $payload->buyerName,
                'buyer_country' => $payload->buyerCountry,
                'currency' => $payload->currency ?? $shop->currency,
                'exchange_rate' => $payload->exchangeRate ?? $shop->exchange_rate,
                // 平台币口径
                'goods_amount' => $payload->goodsAmount,
                'shipping_income' => $payload->shippingIncome,
                'discount_amount' => $payload->discountAmount,
                'platform_commission' => $payload->platformCommission,
                'payment_fee' => $payload->paymentFee,
                'refund_amount' => $payload->refundAmount,
            ];

            // 人民币成本：平台给就覆盖，没给就保留人工录入值
            if ($payload->shippingCost !== null) {
                $data['shipping_cost'] = $payload->shippingCost;
            }
            if ($payload->adCost !== null) {
                $data['ad_cost'] = $payload->adCost;
            }

            if ($isNew) {
                $data['status'] = $this->mapStatus($payload->status)->value;
                $data['source'] = 'api';
                $data['paid_at'] = $payload->paidAt;
                $data['remark'] = $payload->remark;
            }

            $order->fill($data)->save();

            $this->syncItems($order, $payload->items);

            return $isNew ? 'created' : 'updated';
        });
    }

    /**
     * @param  array<int, array{sku: string, product_name: string, quantity: int, price: float, unit_cost: float|null}>  $items
     */
    private function syncItems(Order $order, array $items): void
    {
        $skus = collect($items)->pluck('sku')->filter()->unique()->all();
        $products = Product::whereIn('sku', $skus)->get()->keyBy('sku');

        $seen = [];

        foreach ($items as $item) {
            $product = $products->get($item['sku']);
            $unitCost = $item['unit_cost'] ?? (float) ($product?->cost_price ?? 0);

            $attributes = [
                'product_id' => $product?->id,
                'sku' => $item['sku'],
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
                'unit_cost' => $unitCost, // 成本快照
            ];

            OrderItem::updateOrCreate(
                ['order_id' => $order->id, 'sku' => $item['sku']],
                $attributes
            );

            $seen[] = $item['sku'];
        }

        // 平台侧已删除的明细行一并清理，避免成本被重复计入
        if ($seen !== []) {
            $order->items()->whereNotIn('sku', $seen)->delete();
        }
    }

    private function mapStatus(?string $status): OrderStatus
    {
        return match (strtolower(trim((string) $status))) {
            'paid', 'to_ship', 'ready_to_ship', 'wait_ship', 'processing' => OrderStatus::Paid,
            'shipped', 'in_transit', 'delivering' => OrderStatus::Shipped,
            'completed', 'finished', 'done', 'delivered' => OrderStatus::Completed,
            'cancelled', 'canceled' => OrderStatus::Cancelled,
            'refunding', 'returning' => OrderStatus::Refunding,
            'refunded', 'returned' => OrderStatus::Refunded,
            default => OrderStatus::Pending,
        };
    }
}
