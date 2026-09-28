<?php

namespace App\Services\Logistics;

use App\Models\Shipment;

/**
 * 物流轨迹查询契约
 *
 * 不同承运商（云途 / 燕文 / 4PX / 顺丰国际……）只需实现本接口，
 * 即可接入 TrackingService。当前默认实现为 MockCarrierTracker（演示用），
 * 接入真实承运商 API 时替换绑定即可，业务代码无需改动。
 */
interface CarrierTracker
{
    /**
     * 返回该运单的轨迹时间线
     *
     * @return array<int, array{time: string, status: string, desc: string}>
     */
    public function track(Shipment $shipment): array;
}
