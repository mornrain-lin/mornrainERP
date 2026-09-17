<?php

namespace App\Support;

use App\Models\Order;

/**
 * 利润核算核心 —— mornrainERP 的差异化能力
 *
 * 营收(CNY) = (商品金额 + 运费收入 - 平台补贴 - 平台佣金 - 支付手续费 - 退款) × 汇率
 * 成本(CNY) = 商品采购成本 + 物流成本 + 广告分摊 + 其他成本
 * 毛利(CNY) = 营收 - 成本
 * 毛利率    = 毛利 / 营收 × 100%
 *
 * 目标：把平台佣金、广告费、物流成本、退款四项自动归集，
 *      让「每一单到底赚多少」算得清（对标官网宣传的毛利误差 ≤2%）。
 */
class ProfitCalculator
{
    /**
     * @return array{revenue: float, cost: float, profit: float, margin: float, product_cost: float, platform_net: float, deductions: float}
     */
    public static function compute(
        float $goodsAmount,
        float $shippingIncome,
        float $discountAmount,
        float $platformCommission,
        float $paymentFee,
        float $refundAmount,
        float $exchangeRate,
        float $productCost,
        float $shippingCost,
        float $adCost,
        float $otherCost,
    ): array {
        // 平台币口径的净收入
        $deductions = $discountAmount + $platformCommission + $paymentFee + $refundAmount;
        $platformNet = $goodsAmount + $shippingIncome - $deductions;

        $revenue = round($platformNet * $exchangeRate, 2);
        $cost = round($productCost + $shippingCost + $adCost + $otherCost, 2);
        $profit = round($revenue - $cost, 2);
        $margin = $revenue != 0.0 ? round($profit / $revenue * 100, 2) : 0.0;

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $profit,
            'margin' => $margin,
            'product_cost' => round($productCost, 2),
            'platform_net' => round($platformNet, 2),
            'deductions' => round($deductions, 2),
        ];
    }

    public static function fromOrder(Order $order, ?float $productCost = null): array
    {
        return self::compute(
            goodsAmount: (float) $order->goods_amount,
            shippingIncome: (float) $order->shipping_income,
            discountAmount: (float) $order->discount_amount,
            platformCommission: (float) $order->platform_commission,
            paymentFee: (float) $order->payment_fee,
            refundAmount: (float) $order->refund_amount,
            exchangeRate: (float) $order->exchange_rate ?: 1.0,
            productCost: $productCost ?? $order->resolveProductCost(),
            shippingCost: (float) $order->shipping_cost,
            adCost: (float) $order->ad_cost,
            otherCost: (float) $order->other_cost,
        );
    }
}
