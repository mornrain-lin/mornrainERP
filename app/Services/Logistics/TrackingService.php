<?php

namespace App\Services\Logistics;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Carbon\Carbon;

/**
 * 物流轨迹回写服务
 *
 * 通过 CarrierTracker 拉取轨迹并写回 shipments（events / last_tracked_at），
 * 命中「已签收」时自动推进物流状态为 delivered。
 */
class TrackingService
{
    public function __construct(private readonly CarrierTracker $tracker)
    {
    }

    public function refresh(Shipment $shipment): Shipment
    {
        $events = $this->tracker->track($shipment);
        $latest = end($events);

        $status = $shipment->status;
        $deliveredAt = $shipment->delivered_at;

        if ($latest && str_contains($latest['status'], '签收')) {
            $status = ShipmentStatus::Delivered;
            $deliveredAt = Carbon::parse($latest['time']);
        }

        $shipment->forceFill([
            'events' => $events,
            'last_tracked_at' => now(),
            'status' => $status,
            'delivered_at' => $deliveredAt,
        ])->save();

        return $shipment;
    }
}
