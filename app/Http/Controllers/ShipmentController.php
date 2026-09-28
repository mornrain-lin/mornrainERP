<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\Logistics\TrackingService;
use Illuminate\Http\RedirectResponse;

/**
 * 物流轨迹回写
 *
 * 单条刷新 / 批量刷新在途运单，轨迹由 TrackingService 拉取并写回 shipments。
 */
class ShipmentController extends Controller
{
    public function track(Shipment $shipment, TrackingService $service): RedirectResponse
    {
        $service->refresh($shipment);

        return back()->with('ok', "运单 {$shipment->tracking_no} 轨迹已更新");
    }

    public function trackAll(TrackingService $service): RedirectResponse
    {
        $shipments = Shipment::where('status', '!=', ShipmentStatus::Delivered->value)->get();

        $count = 0;
        foreach ($shipments as $shipment) {
            $service->refresh($shipment);
            $count++;
        }

        return back()->with('ok', "已刷新 {$count} 条在途运单轨迹" . ($count === 0 ? '（没有在途运单）' : ''));
    }
}
