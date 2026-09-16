<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            ['shopee', 'Shopee', '东南亚', 6.0, 2.0, '#ee4d2d'],
            ['lazada', 'Lazada', '东南亚', 5.0, 2.0, '#0f146d'],
            ['tiktok', 'TikTok Shop', '全球', 5.0, 1.0, '#111111'],
            ['amazon', 'Amazon', '北美/欧洲', 15.0, 1.5, '#ff9900'],
            ['temu', 'Temu', '北美/欧洲', 8.0, 1.0, '#fb7701'],
            ['aliexpress', 'AliExpress', '全球', 8.0, 2.0, '#e62e04'],
            ['shopify', 'Shopify 独立站', '全球', 0.0, 2.9, '#5e8e3e'],
            ['ebay', 'eBay', '全球', 12.0, 2.0, '#e53238'],
            ['wish', 'Wish', '全球', 15.0, 2.0, '#2fb7ec'],
            ['walmart', 'Walmart', '北美', 12.0, 1.5, '#0071dc'],
            ['mercado', 'Mercado Libre', '拉美', 16.0, 2.0, '#ffe600'],
            ['rakuten', 'Rakuten', '日本', 10.0, 2.0, '#bf0000'],
            ['coupang', 'Coupang', '韩国', 11.0, 1.5, '#c8102e'],
            ['noon', 'Noon', '中东', 12.0, 2.0, '#feee00'],
            ['zoodmall', 'ZoodMall', '中东', 10.0, 2.0, '#00a4e4'],
            ['jumia', 'Jumia', '非洲', 15.0, 2.0, '#f68b1e'],
            ['ozon', 'Ozon', '俄罗斯', 12.0, 1.5, '#005bff'],
            ['wildberries', 'Wildberries', '俄罗斯', 10.0, 2.0, '#cb11ab'],
            ['allegro', 'Allegro', '波兰', 8.0, 1.5, '#ff5a00'],
            ['etsy', 'Etsy', '全球', 6.5, 3.0, '#f1641e'],
            ['zalora', 'Zalora', '东南亚', 10.0, 2.0, '#5c2d91'],
        ];

        foreach ($platforms as [$code, $name, $region, $commission, $fee, $color]) {
            Platform::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'region' => $region,
                    'commission_rate' => $commission,
                    'payment_fee_rate' => $fee,
                    'color' => $color,
                    'is_active' => true,
                ]
            );
        }
    }
}
