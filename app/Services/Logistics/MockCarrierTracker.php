<?php

namespace App\Services\Logistics;

use App\Models\Shipment;
use Carbon\Carbon;

/**
 * 演示用物流轨迹生成器
 *
 * 没有真实承运商 API 时，依据发货时间推算一条合理的跨境物流时间线：
 * 揽收 → 干线运输 → 到达目的国 → 派送 → 签收。
 * 接入真实 API 后，替换 AppServiceProvider 中的绑定即可。
 */
class MockCarrierTracker implements CarrierTracker
{
    /** 各节点距发货的天数偏移 */
    private const STEPS = [
        0 => ['status' => '已揽收', 'desc' => '承运商已揽收包裹'],
        1 => ['status' => '运输中', 'desc' => '干线运输中'],
        3 => ['status' => '到达目的国', 'desc' => '到达目的国分拣中心'],
        4 => ['status' => '派送中', 'desc' => '本地快递员正在派送'],
        5 => ['status' => '已签收', 'desc' => '买家已签收'],
    ];

    public function track(Shipment $shipment): array
    {
        $shipped = $shipment->shipped_at
            ? Carbon::parse($shipment->shipped_at)
            : Carbon::now()->subDays(3);
        $now = Carbon::now();
        $elapsed = (int) $shipped->diffInDays($now);

        $country = $shipment->order?->buyer_country ?? '目的国';
        $events = [];

        foreach (self::STEPS as $offset => $step) {
            if ($elapsed < $offset) {
                continue;
            }
            $events[] = [
                'time' => $shipped->copy()->addDays($offset)->format('Y-m-d H:i'),
                'status' => $step['status'],
                'desc' => $step['status'] === '到达目的国'
                    ? "到达 {$country} 分拣中心"
                    : $step['desc'],
            ];
        }

        if ($events === []) {
            $events[] = [
                'time' => $shipped->format('Y-m-d H:i'),
                'status' => '已揽收',
                'desc' => '承运商已揽收包裹',
            ];
        }

        return $events;
    }
}
