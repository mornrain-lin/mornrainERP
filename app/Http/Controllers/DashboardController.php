<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Platform;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        // ---- 今日经营概览 ----
        $todayOrders = Order::withAggregates()->whereDate('created_at', $today)->get();
        $todayRevenue = 0.0;
        $todayProfit = 0.0;
        foreach ($todayOrders as $o) {
            if (! $o->status->countsAsRevenue()) {
                continue;
            }
            $p = $o->profit();
            $todayRevenue += $p['revenue'];
            $todayProfit += $p['profit'];
        }

        // ---- 本月累计 ----
        $monthOrders = Order::withAggregates()->where('created_at', '>=', $monthStart)->get();
        $monthRevenue = 0.0;
        $monthProfit = 0.0;
        foreach ($monthOrders as $o) {
            if (! $o->status->countsAsRevenue()) {
                continue;
            }
            $p = $o->profit();
            $monthRevenue += $p['revenue'];
            $monthProfit += $p['profit'];
        }

        $kpi = [
            'today_orders' => $todayOrders->count(),
            'today_revenue' => round($todayRevenue, 2),
            'today_profit' => round($todayProfit, 2),
            'month_orders' => $monthOrders->count(),
            'month_revenue' => round($monthRevenue, 2),
            'month_profit' => round($monthProfit, 2),
            'month_margin' => $monthRevenue > 0 ? round($monthProfit / $monthRevenue * 100, 1) : 0,
            'pending_ship' => Order::where('status', OrderStatus::Paid)->count(),
            'refunding' => Order::where('status', OrderStatus::Refunding)->count(),
        ];

        // ---- 近 7 日趋势 ----
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $rows = Order::withAggregates()->whereDate('created_at', $day)->get();
            $rev = 0.0;
            $pro = 0.0;
            foreach ($rows as $o) {
                if (! $o->status->countsAsRevenue()) {
                    continue;
                }
                $p = $o->profit();
                $rev += $p['revenue'];
                $pro += $p['profit'];
            }
            $trend[] = [
                'date' => $day->format('m-d'),
                'orders' => $rows->count(),
                'revenue' => round($rev, 2),
                'profit' => round($pro, 2),
            ];
        }

        // ---- 平台分布（本月订单数与营收） ----
        $platformStats = [];
        foreach (Platform::orderBy('id')->get() as $platform) {
            $rows = $monthOrders->where('platform_id', $platform->id);
            $rev = 0.0;
            $pro = 0.0;
            foreach ($rows as $o) {
                if (! $o->status->countsAsRevenue()) {
                    continue;
                }
                $p = $o->profit();
                $rev += $p['revenue'];
                $pro += $p['profit'];
            }
            $platformStats[] = [
                'name' => $platform->name,
                'color' => $platform->color,
                'orders' => $rows->count(),
                'revenue' => round($rev, 2),
                'profit' => round($pro, 2),
            ];
        }
        $platformMax = max(1, collect($platformStats)->max('revenue'));

        // ---- 待办：最新待发货订单 ----
        $pendingOrders = Order::with(['platform', 'shop'])
            ->withAggregates()
            ->where('status', OrderStatus::Paid)
            ->orderByDesc('paid_at')
            ->limit(6)
            ->get();

        // ---- 最新订单 ----
        $latestOrders = Order::with(['platform', 'shop'])
            ->withAggregates()
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return view('dashboard', compact(
            'kpi', 'trend', 'platformStats', 'platformMax', 'pendingOrders', 'latestOrders'
        ));
    }
}
