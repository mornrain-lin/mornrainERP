<?php

namespace App\Console\Commands;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\Logistics\TrackingService;
use Illuminate\Console\Command;

/**
 * 刷新物流轨迹（可挂 cron 定时执行）
 *
 *   php artisan shipments:track          # 仅刷新在途运单
 *   php artisan shipments:track --all    # 含已签收
 */
class TrackShipments extends Command
{
    protected $signature = 'shipments:track {--all : 包含已签收的运单}';

    protected $description = '拉取并回写物流轨迹';

    public function handle(TrackingService $service): int
    {
        $query = Shipment::query();
        if (! $this->option('all')) {
            $query->where('status', '!=', ShipmentStatus::Delivered->value);
        }

        $shipments = $query->get();
        if ($shipments->isEmpty()) {
            $this->info('没有需要刷新的运单');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($shipments->count());
        foreach ($shipments as $shipment) {
            $service->refresh($shipment);
            $bar->advance();
        }
        $bar->finish();

        $this->newLine();
        $this->info("已刷新 {$shipments->count()} 条运单轨迹");

        return self::SUCCESS;
    }
}
