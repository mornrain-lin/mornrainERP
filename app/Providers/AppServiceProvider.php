<?php

namespace App\Providers;

use App\Services\Logistics\CarrierTracker;
use App\Services\Logistics\MockCarrierTracker;
use App\Services\Logistics\TrackingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 物流轨迹：默认使用演示生成器，接入真实承运商 API 时替换此绑定
        $this->app->bind(CarrierTracker::class, MockCarrierTracker::class);
        $this->app->bind(TrackingService::class, fn ($app) => new TrackingService($app->make(CarrierTracker::class)));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
