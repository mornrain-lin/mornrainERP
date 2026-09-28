<?php

namespace App\Services\PlatformSync;

use Carbon\CarbonInterface;

/**
 * 平台订单的标准形态
 *
 * 各平台字段命名千差万别（Shopee 的 ordersn、TikTok 的 order_id、Amazon 的
 * AmazonOrderId……），连接器层统一翻译成这个结构，落库逻辑就只有一份。
 */
final class NormalizedOrder
{
    /** @param array<int, array{sku: string, product_name: string, quantity: int, price: float, unit_cost: float|null}> $items */
    public function __construct(
        public readonly string $orderNo,
        public readonly array $items = [],
        public readonly ?string $buyerName = null,
        public readonly ?string $buyerCountry = null,
        public readonly ?string $currency = null,
        public readonly ?float $exchangeRate = null,
        public readonly float $goodsAmount = 0.0,
        public readonly float $shippingIncome = 0.0,
        public readonly float $discountAmount = 0.0,
        public readonly float $platformCommission = 0.0,
        public readonly float $paymentFee = 0.0,
        public readonly float $refundAmount = 0.0,
        public readonly ?float $shippingCost = null,
        public readonly ?float $adCost = null,
        public readonly ?string $status = null,
        public readonly ?CarbonInterface $paidAt = null,
        public readonly ?string $remark = null,
    ) {
    }

    /**
     * 从平台原始数组构造，未知字段一律安全降级为 0 / null
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $items = [];
        foreach ((array) ($raw['items'] ?? []) as $it) {
            $sku = trim((string) ($it['sku'] ?? $it['seller_sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $items[] = [
                'sku' => $sku,
                'product_name' => (string) ($it['product_name'] ?? $it['name'] ?? $sku),
                'quantity' => max(1, (int) ($it['quantity'] ?? $it['qty'] ?? 1)),
                'price' => round((float) ($it['price'] ?? $it['unit_price'] ?? 0), 2),
                'unit_cost' => isset($it['unit_cost']) ? round((float) $it['unit_cost'], 2) : null,
            ];
        }

        $paidAt = $raw['paid_at'] ?? $raw['create_time'] ?? $raw['created_at'] ?? null;

        return new self(
            orderNo: trim((string) ($raw['order_no'] ?? $raw['order_id'] ?? $raw['ordersn'] ?? '')),
            items: $items,
            buyerName: isset($raw['buyer_name']) ? (string) $raw['buyer_name'] : ($raw['buyer']['name'] ?? null),
            buyerCountry: isset($raw['buyer_country']) ? (string) $raw['buyer_country'] : ($raw['buyer']['country'] ?? null),
            currency: isset($raw['currency']) ? (string) $raw['currency'] : null,
            exchangeRate: isset($raw['exchange_rate']) ? (float) $raw['exchange_rate'] : null,
            goodsAmount: round((float) ($raw['goods_amount'] ?? $raw['total_amount'] ?? 0), 2),
            shippingIncome: round((float) ($raw['shipping_income'] ?? $raw['shipping_fee'] ?? 0), 2),
            discountAmount: round((float) ($raw['discount_amount'] ?? $raw['discount'] ?? 0), 2),
            platformCommission: round((float) ($raw['platform_commission'] ?? $raw['commission'] ?? 0), 2),
            paymentFee: round((float) ($raw['payment_fee'] ?? 0), 2),
            refundAmount: round((float) ($raw['refund_amount'] ?? 0), 2),
            shippingCost: isset($raw['shipping_cost']) ? round((float) $raw['shipping_cost'], 2) : null,
            adCost: isset($raw['ad_cost']) ? round((float) $raw['ad_cost'], 2) : null,
            status: isset($raw['status']) ? (string) $raw['status'] : null,
            paidAt: $paidAt ? now()->parse((string) $paidAt) : null,
            remark: isset($raw['remark']) ? (string) $raw['remark'] : null,
        );
    }

    public function isValid(): bool
    {
        return $this->orderNo !== '';
    }
}
