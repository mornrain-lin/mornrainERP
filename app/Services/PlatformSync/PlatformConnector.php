<?php

namespace App\Services\PlatformSync;

use App\Models\Shop;
use Carbon\CarbonInterface;

/**
 * 平台连接器契约
 *
 * 新增一个平台 = 新增一个实现类并在 PlatformConnectorFactory 里注册，
 * 落库、去重、成本核算等逻辑完全复用，不需要改业务代码。
 */
interface PlatformConnector
{
    /**
     * 拉取时间窗内的订单（已翻译成 NormalizedOrder）
     *
     * @return array<int, NormalizedOrder>
     *
     * @throws \App\Services\PlatformSync\ConnectorException
     */
    public function fetchOrders(Shop $shop, CarbonInterface $from, CarbonInterface $to): array;

    /** 连接器展示名 */
    public function label(): string;
}
