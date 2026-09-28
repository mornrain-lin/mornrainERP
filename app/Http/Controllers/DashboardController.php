<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Platform;
use App\Services\Inventory\StockService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** 趋势图展示天数 */
    private const TREND_DAYS = 14;

    public function index(Request $request, StockService $stock)
    {
        $now = Carbon::now();
        $today = Carbon::today();

        // ---- 今日 / 昨日 ----
        $todayStat = $this->summarize($this->ordersBetween($today, $now));
        $yesterdayStat = $this->summarize($this->ordersBetween(Carbon::yesterday(), Carbon::yesterday()->endOfDay()));

        // ---- 本月 / 上月（同期） ----
        $monthStart = $now->copy()->startOfMonth();
        $prevMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $prevMonthEnd = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $monthStat = $this->summarize($this->ordersBetween($monthStart, $now));
        $prevMonthStat = $this->summarize($this->ordersBetween($prevMonthStart, $prevMonthEnd));

        $kpi = [
            'today_orders' => $todayStat['orders'],
            'today_revenue' => $todayStat['revenue'],
            'today_profit' => $todayStat['profit'],
            'month_orders' => $monthStat['orders'],
            'month_revenue' => $monthStat['revenue'],
            'month_profit' => $monthStat['profit'],
            'month_margin' => $monthStat['revenue'] > 0
                ? round($monthStat['profit'] / $monthStat['revenue'] * 100, 1)
                : 0.0,
            'pending_ship' => Order::where('status', OrderStatus::Paid)->count(),
            'refunding' => Order::where('status', OrderStatus::Refunding)->count(),
            // 环比：今日 vs 昨日、本月 vs 上月同期
            'dod' => [
                'orders' => $this->growth($todayStat['orders'], $yesterdayStat['orders']),
                'revenue' => $this->growth($todayStat['revenue'], $yesterdayStat['revenue']),
                'profit' => $this->growth($todayStat['profit'], $yesterdayStat['profit']),
            ],
            'mom' => [
                'orders' => $this->growth($monthStat['orders'], $prevMonthStat['orders']),
                'revenue' => $this->growth($monthStat['revenue'], $prevMonthStat['revenue']),
                'profit' => $this->growth($monthStat['profit'], $prevMonthStat['profit']),
            ],
        ];

        // ---- 近 14 日趋势 ----
        $trend = [];
        for ($i = self::TREND_DAYS - 1; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $stat = $this->summarize($this->ordersBetween($day, $day->copy()->endOfDay()));
            $trend[] = [
                'date' => $day->format('m-d'),
                'orders' => $stat['orders'],
                'revenue' => $stat['revenue'],
                'profit' => $stat['profit'],
            ];
        }

        // ---- 平台分布 ----
        $monthOrders = $this->ordersBetween($monthStart, $now);
        $platformStats = [];
        foreach (Platform::orderBy('id')->get() as $platform) {
            $stat = $this->summarize($monthOrders->where('platform_id', $platform->id));
            $platformStats[] = [
                'name' => $platform->name,
                'color' => $platform->color,
                'orders' => $stat['orders'],
                'revenue' => $stat['revenue'],
                'profit' => $stat['profit'],
                'margin' => $stat['revenue'] > 0 ? round($stat['profit'] / $stat['revenue'] * 100, 1) : 0.0,
            ];
        }
        $platformMax = max(1, collect($platformStats)->max('revenue'));

        // ---- 库存预警 ----
        $lowStock = $stock->lowStockProducts();

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
            'kpi', 'trend', 'platformStats', 'platformMax', 'pendingOrders', 'latestOrders', 'lowStock'
        ));
    }

    /** 取时间窗内的订单（统一带成本聚合，避免 N+1） */
    private function ordersBetween(Carbon $from, Carbon $to): Collection
    {
        return Order::withAggregates()
            ->whereBetween('created_at', [$from, $to])
            ->get();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{orders: int, revenue: float, profit: float}
     */
    private function summarize(Collection $orders): array
    {
        $revenue = 0.0;
        $profit = 0.0;

        foreach ($orders as $order) {
            if (! $order->status->countsAsRevenue()) {
                continue;
            }
            $p = $order->profit();
            $revenue += $p['revenue'];
            $profit += $p['profit'];
        }

        return [
            'orders' => $orders->count(),
            'revenue' => round($revenue, 2),
            'profit' => round($profit, 2),
        ];
    }

    /** 环比增长率：基期为 0 时返回 null（页面显示 —） */
    private function growth(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous == 0.0) {
            return null;
        }

        return round(((float) $current - (float) $previous) / abs((float) $previous) * 100, 1);
    }
}
