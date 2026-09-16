<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Platform;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * 利润报表：按平台 / 店铺 / 日期三个维度聚合营收、成本、毛利与毛利率
     */
    public function profit(Request $request)
    {
        $from = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', Carbon::today()->toDateString());

        $orders = Order::query()
            ->with(['platform', 'shop'])
            ->withAggregates()
            ->whereBetween('created_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->get();

        $valid = $orders->filter(fn (Order $o) => $o->status->countsAsRevenue());

        // 汇总
        $totalRevenue = 0.0;
        $totalCost = 0.0;
        $totalProfit = 0.0;
        $costBreakdown = ['商品采购' => 0.0, '物流' => 0.0, '广告' => 0.0, '其他' => 0.0];
        foreach ($valid as $o) {
            $p = $o->profit();
            $totalRevenue += $p['revenue'];
            $totalProfit += $p['profit'];
            $costBreakdown['商品采购'] += $p['product_cost'];
            $costBreakdown['物流'] += (float) $o->shipping_cost;
            $costBreakdown['广告'] += (float) $o->ad_cost;
            $costBreakdown['其他'] += (float) $o->other_cost;
        }
        $totalCost = array_sum($costBreakdown);
        $margin = $totalRevenue > 0 ? round($totalProfit / $totalRevenue * 100, 2) : 0;

        // 按平台
        $byPlatform = [];
        foreach (Platform::orderBy('id')->get() as $platform) {
            $rows = $valid->where('platform_id', $platform->id);
            $rev = $pro = 0.0;
            foreach ($rows as $o) {
                $p = $o->profit();
                $rev += $p['revenue'];
                $pro += $p['profit'];
            }
            $byPlatform[] = [
                'name' => $platform->name,
                'color' => $platform->color,
                'orders' => $rows->count(),
                'revenue' => round($rev, 2),
                'profit' => round($pro, 2),
                'margin' => $rev > 0 ? round($pro / $rev * 100, 1) : 0,
            ];
        }

        // 按店铺
        $byShop = [];
        foreach (Shop::with('platform')->orderBy('id')->get() as $shop) {
            $rows = $valid->where('shop_id', $shop->id);
            if ($rows->isEmpty()) {
                continue;
            }
            $rev = $pro = 0.0;
            foreach ($rows as $o) {
                $p = $o->profit();
                $rev += $p['revenue'];
                $pro += $p['profit'];
            }
            $byShop[] = [
                'name' => $shop->display_name,
                'orders' => $rows->count(),
                'revenue' => round($rev, 2),
                'profit' => round($pro, 2),
                'margin' => $rev > 0 ? round($pro / $rev * 100, 1) : 0,
            ];
        }

        // 按日
        $byDay = [];
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        while ($cursor->lte($end)) {
            $rows = $valid->filter(fn (Order $o) => $o->created_at->isSameDay($cursor));
            $rev = $pro = 0.0;
            foreach ($rows as $o) {
                $p = $o->profit();
                $rev += $p['revenue'];
                $pro += $p['profit'];
            }
            $byDay[] = ['date' => $cursor->format('m-d'), 'revenue' => round($rev, 2), 'profit' => round($pro, 2)];
            $cursor->addDay();
        }

        // 亏损订单 Top
        $lossOrders = $valid
            ->map(fn (Order $o) => ['order' => $o, 'profit' => $o->profit()])
            ->filter(fn ($x) => $x['profit']['profit'] < 0)
            ->sortBy('profit.profit')
            ->take(8)
            ->values();

        // SKU 毛利贡献 Top
        $skuStats = [];
        foreach ($valid as $o) {
            foreach ($o->items as $it) {
                $key = $it->sku;
                $skuStats[$key] ??= ['sku' => $key, 'name' => $it->product_name, 'qty' => 0, 'revenue' => 0.0, 'cost' => 0.0];
                $skuStats[$key]['qty'] += $it->quantity;
                $skuStats[$key]['revenue'] += round($it->line_total * (float) $o->exchange_rate, 2);
                $skuStats[$key]['cost'] += $it->line_cost;
            }
        }
        $skuTop = collect($skuStats)
            ->map(function ($s) {
                $s['profit'] = round($s['revenue'] - $s['cost'], 2);
                $s['margin'] = $s['revenue'] > 0 ? round($s['profit'] / $s['revenue'] * 100, 1) : 0;

                return $s;
            })
            ->sortByDesc('profit')
            ->take(10)
            ->values();

        return view('reports.profit', compact(
            'from', 'to', 'totalRevenue', 'totalCost', 'totalProfit', 'margin',
            'costBreakdown', 'byPlatform', 'byShop', 'byDay', 'lossOrders', 'skuTop'
        ) + ['orderCount' => $valid->count()]);
    }
}
