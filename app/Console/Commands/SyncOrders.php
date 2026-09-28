<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\PlatformSync\OrderSyncService;
use Illuminate\Console\Command;

/**
 * 从平台 OpenAPI 拉取订单
 *
 * 手动：php artisan orders:sync --shop=1 --days=3
 * 定时：在 routes/console.php 里 $schedule->command('orders:sync --all')->hourly();
 */
class SyncOrders extends Command
{
    protected $signature = 'orders:sync
                            {--shop= : 指定店铺 ID}
                            {--days=3 : 拉取最近 N 天更新的订单}
                            {--all : 同步所有已开启自动拉单的店铺}';

    protected $description = '从平台 OpenAPI 拉取订单并落库（自动去重、按 SKU 快照成本）';

    public function handle(OrderSyncService $service): int
    {
        $days = max(1, (int) $this->option('days'));

        if ($this->option('all')) {
            $results = $service->syncAll('command');

            if ($results === []) {
                $this->warn('没有开启自动拉单的店铺，请先在「平台对接」里配置并启用。');

                return self::SUCCESS;
            }

            foreach ($results as $row) {
                $this->line(sprintf(
                    '<info>%s</info> %s：%s',
                    $row['shop']->name,
                    $row['result']['status'] === 'success' ? '成功' : '失败',
                    $row['result']['message']
                ));
            }

            return self::SUCCESS;
        }

        $shopId = $this->option('shop');
        $shop = $shopId
            ? Shop::find($shopId)
            : Shop::where('sync_enabled', true)->first();

        if (! $shop) {
            $this->error('未找到目标店铺，请用 --shop=ID 指定。');

            return self::FAILURE;
        }

        $result = $service->syncShop($shop, now()->subDays($days), now(), 'command');

        $this->line(sprintf('%s：%s', $shop->name, $result['message']));

        return $result['status'] === 'success' ? self::SUCCESS : self::FAILURE;
    }
}
