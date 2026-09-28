<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\StockService;
use Illuminate\Database\Seeder;

/**
 * 为演示商品铺一遍库存与安全库存
 *
 * 幂等：已经有过流水（说明人工维护过）的商品不会被覆盖。
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        /** @var StockService $stock */
        $stock = app(StockService::class);

        $products = Product::where('stock', 0)
            ->whereDoesntHave('stockMovements')
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        foreach ($products as $index => $product) {
            // 第一个 SKU 故意做成缺货，方便看到预警与采购建议效果
            $quantity = $index === 0 ? 3 : random_int(20, 180);
            $safety = $index === 0 ? 30 : random_int(10, 40);

            $product->forceFill(['safety_stock' => $safety])->save();

            if ($quantity > 0) {
                $stock->adjust($product, $quantity, StockMovement::TYPE_IN, '演示数据初始入库');
            }
        }

        $this->command?->info("已为 {$products->count()} 个 SKU 初始化库存与安全库存。");
    }
}
