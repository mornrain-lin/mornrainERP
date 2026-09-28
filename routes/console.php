<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| mornrainERP 定时任务
|--------------------------------------------------------------------------
| 自动拉单默认关闭（避免没配凭证时空跑产生噪音日志）。
| 开启步骤：
|   1) .env 设置 SYNC_SCHEDULE_ENABLED=true
|   2) 系统 cron 每分钟跑一次调度器：
|      * * * * * php /path/to/mornrainERP/artisan schedule:run >> /dev/null 2>&1
|   3) 本地调试可直接 php artisan schedule:work
| 每次执行结果都会写入 sync_logs，可在「平台对接」页查看。
*/
if (env('SYNC_SCHEDULE_ENABLED', false)) {
    Schedule::command('orders:sync --all')
        ->hourly()
        ->withoutOverlapping();

    // 每天刷新在途运单轨迹（回写 shipments）
    Schedule::command('shipments:track')
        ->dailyAt('08:00')
        ->withoutOverlapping();
}
