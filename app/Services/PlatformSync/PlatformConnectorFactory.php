<?php

namespace App\Services\PlatformSync;

use App\Models\Shop;

/**
 * 连接器工厂
 *
 * 目前内置通用 REST 连接器；后续接入具体平台时，
 * 在这里按平台标识注册各自的 PlatformConnector 实现即可。
 */
class PlatformConnectorFactory
{
    /** @var array<string, class-string<PlatformConnector>> */
    protected array $map = [
        // 'shopee' => ShopeeConnector::class,
        // 'tiktok' => TikTokConnector::class,
    ];

    public function make(Shop $shop): PlatformConnector
    {
        $key = strtolower((string) ($shop->platform?->code ?? $shop->platform?->name ?? ''));

        $class = $this->map[$key] ?? GenericRestConnector::class;

        return new $class();
    }

    /** 供页面展示：当前店铺用的是哪个连接器 */
    public function labelFor(Shop $shop): string
    {
        return $this->make($shop)->label();
    }
}
