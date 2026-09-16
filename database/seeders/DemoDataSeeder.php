<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Shop;
use Illuminate\Database\Seeder;

/**
 * 演示数据：让概览 / 订单 / 利润报表一打开就有内容
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- 店铺 ----------
        $shopDefs = [
            ['shopee', '晨雨官方店', 'MY', 'MYR', 0.65, 'mr_my_official'],
            ['shopee', '晨雨家居馆', 'TH', 'THB', 0.20, 'mr_th_home'],
            ['lazada', 'MornRain Home', 'SG', 'SGD', 5.35, 'mornrain_sg'],
            ['tiktok', '晨雨跨境严选', 'US', 'USD', 7.20, 'mornrain_us'],
            ['amazon', 'MornRain US Store', 'US', 'USD', 7.20, 'mornrain_amzn'],
            ['temu', '晨雨优选', 'US', 'USD', 7.20, 'mornrain_temu'],
            ['shopify', 'mornrain.com 独立站', 'US', 'USD', 7.20, 'mornrain_dtc'],
        ];
        $shops = [];
        foreach ($shopDefs as [$code, $name, $region, $currency, $rate, $account]) {
            $platform = Platform::where('code', $code)->first();
            if (! $platform) {
                continue;
            }
            $shops[] = Shop::updateOrCreate(
                ['platform_id' => $platform->id, 'name' => $name],
                ['seller_account' => $account, 'region' => $region, 'currency' => $currency, 'exchange_rate' => $rate, 'is_active' => true]
            );
        }

        // ---------- 商品 ----------
        $productDefs = [
            ['MR-0001', '硅胶折叠水杯 750ml', '户外', 18.50, 260],
            ['MR-0002', '便携露营灯 USB 充电', '户外', 32.00, 310],
            ['MR-0003', '多功能收纳化妆包', '家居', 12.80, 180],
            ['MR-0004', '厨房硅胶铲套装 6 件', '家居', 21.50, 420],
            ['MR-0005', '磁吸手机支架', '3C', 9.60, 90],
            ['MR-0006', '蓝牙 5.3 耳机 运动款', '3C', 68.00, 150],
            ['MR-0007', '宠物自动喂食器', '宠物', 55.00, 680],
            ['MR-0008', '瑜伽拉伸弹力带 3 件', '运动', 14.20, 220],
            ['MR-0009', '车载收纳挂袋', '汽车', 16.90, 240],
            ['MR-0010', '女士防晒空顶帽', '服饰', 11.30, 130],
            ['MR-0011', '桌面理线收纳盒', '家居', 13.70, 290],
            ['MR-0012', '儿童硅胶餐盘吸盘款', '母婴', 19.90, 200],
        ];
        $products = [];
        foreach ($productDefs as [$sku, $name, $category, $cost, $weight]) {
            $products[] = Product::updateOrCreate(
                ['sku' => $sku],
                ['name' => $name, 'category' => $category, 'cost_price' => $cost, 'weight_g' => $weight, 'is_active' => true]
            );
        }

        if (! $shops || ! $products) {
            return;
        }

        if (Order::query()->exists()) {
            return; // 已有订单则不重复灌数据
        }

        // ---------- 订单 ----------
        $carriers = Shipment::CARRIERS;
        $statusPool = [
            OrderStatus::Pending,
            OrderStatus::Paid, OrderStatus::Paid, OrderStatus::Paid,
            OrderStatus::Shipped, OrderStatus::Shipped,
            OrderStatus::Completed, OrderStatus::Completed, OrderStatus::Completed,
            OrderStatus::Refunding,
            OrderStatus::Cancelled,
        ];

        $total = 180;

        for ($i = 0; $i < $total; $i++) {
            $shop = $shops[array_rand($shops)];
            $platform = $shop->platform;
            $rate = (float) $shop->exchange_rate;
            $currency = $shop->currency;

            // 时间集中在近 30 天，今天稍多，形成趋势
            $daysAgo = (int) floor(abs(gaussRandom(0, 8)));
            $daysAgo = min($daysAgo, 29);
            $createdAt = now()->subDays($daysAgo)->subMinutes(random_int(0, 1400));

            $status = $statusPool[array_rand($statusPool)];

            // 明细
            $lines = [];
            $goodsLocal = 0.0;
            $itemCount = random_int(1, 3);
            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products[array_rand($products)];
                $qty = random_int(1, 3);
                // 售价 ≈ 成本 / 汇率 × 加价倍数
                $markup = random_int(260, 420) / 100;
                $price = max(1.0, round(($product->cost_price * $markup) / max($rate, 0.0001), 2));
                $lines[] = ['product' => $product, 'qty' => $qty, 'price' => $price];
                $goodsLocal += $price * $qty;
            }
            $goodsLocal = round($goodsLocal, 2);
            $shippingIncome = round(random_int(0, 40) / 10, 2);
            $discount = random_int(0, 10) === 0 ? round($goodsLocal * 0.05, 2) : 0.0;
            $commission = round($goodsLocal * $platform->commission_rate / 100, 2);
            $paymentFee = round($goodsLocal * $platform->payment_fee_rate / 100, 2);
            $isRefund = $status === OrderStatus::Refunding;
            $refund = $isRefund ? round($goodsLocal * (random_int(5, 10) / 10), 2) : 0.0;

            $shippingCost = round(random_int(90, 340) / 10, 2);
            $adCost = random_int(0, 4) === 0 ? round(random_int(30, 200) / 10, 2) : 0.0;
            $otherCost = random_int(0, 6) === 0 ? round(random_int(10, 80) / 10, 2) : 0.0;

            $order = Order::create([
                'order_no' => strtoupper($platform->code) . '-' . $createdAt->format('ymd') . '-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'platform_id' => $platform->id,
                'shop_id' => $shop->id,
                'buyer_name' => randomBuyer(),
                'buyer_country' => $shop->region,
                'currency' => $currency,
                'exchange_rate' => $rate,
                'goods_amount' => $goodsLocal,
                'shipping_income' => $shippingIncome,
                'discount_amount' => $discount,
                'platform_commission' => $commission,
                'payment_fee' => $paymentFee,
                'refund_amount' => $refund,
                'shipping_cost' => $shippingCost,
                'ad_cost' => $adCost,
                'other_cost' => $otherCost,
                'status' => $status,
                'source' => ['api', 'api', 'api', 'import', 'manual'][array_rand(['api', 'api', 'api', 'import', 'manual'])],
                'paid_at' => $status !== OrderStatus::Pending ? $createdAt->copy()->addMinutes(random_int(1, 60)) : null,
                'shipped_at' => in_array($status, [OrderStatus::Shipped, OrderStatus::Completed], true) ? $createdAt->copy()->addHours(random_int(4, 40)) : null,
                'completed_at' => $status === OrderStatus::Completed ? $createdAt->copy()->addDays(random_int(3, 9)) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'sku' => $line['product']->sku,
                    'product_name' => $line['product']->name,
                    'quantity' => $line['qty'],
                    'unit_price' => $line['price'],
                    'unit_cost' => $line['product']->cost_price,
                ]);
            }

            if (in_array($status, [OrderStatus::Shipped, OrderStatus::Completed], true)) {
                Shipment::create([
                    'order_id' => $order->id,
                    'carrier' => $carriers[array_rand($carriers)],
                    'tracking_no' => 'MR' . strtoupper(bin2hex(random_bytes(5))),
                    'status' => $status === OrderStatus::Completed ? ShipmentStatus::Delivered : ShipmentStatus::InTransit,
                    'cost' => $shippingCost,
                    'shipped_at' => $order->shipped_at,
                    'delivered_at' => $status === OrderStatus::Completed ? $order->completed_at : null,
                ]);
            }
        }
    }
}

/**
 * 近似正态分布随机数（Box-Muller），让订单量呈自然聚集
 */
function gaussRandom(float $mean = 0, float $sd = 1): float
{
    $u1 = mt_rand() / mt_getrandmax();
    $u2 = mt_rand() / mt_getrandmax();
    $u1 = max($u1, 1e-9);
    $z = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);

    return $z * $sd + $mean;
}

function randomBuyer(): string
{
    $first = ['Ahmad', 'Siti', 'Nguyen', 'Somchai', 'Putra', 'Maria', 'Tan', 'Chen', 'Rahman', 'Lina', 'Kenji', 'Dewi', 'Rio', 'Hakim', 'Nadia', 'Jason', 'Emily', 'Daniel'];
    $last = ['Ali', 'Budi', 'Mai', 'Ken', 'Joy', 'Ayu', 'Rio', 'Dewi', 'Hakim', 'Nadia', 'Lee', 'Santos', 'Kumar', 'Wong'];

    return $first[array_rand($first)] . ' ' . $last[array_rand($last)];
}
