@extends('layouts.app')

@section('title', '利润报表')
@section('desc', $from . ' 至 ' . $to . ' · 平台 / 店铺 / 日期三维度聚合')

@section('actions')
    <a class="btn" href="{{ route('orders.export', ['date_from' => $from, 'date_to' => $to]) }}">↓ 导出明细</a>
@endsection

@section('content')
    @php $maxDay = max(1, collect($byDay)->max('revenue')); @endphp

    {{-- 时间筛选 --}}
    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('reports.profit') }}" class="filter-bar">
                <div class="field"><label>起始日期</label><input type="date" name="date_from" value="{{ $from }}"></div>
                <div class="field"><label>结束日期</label><input type="date" name="date_to" value="{{ $to }}"></div>
                <div class="field" style="flex-direction:row;gap:8px">
                    <button class="btn btn-primary" type="submit">生成报表</button>
                    <a class="btn" href="{{ route('reports.profit') }}">本月</a>
                </div>
            </form>
        </div>
    </div>

    {{-- 汇总 KPI --}}
    <div class="kpi-grid" style="margin-top:18px">
        <div class="kpi kpi-accent"><div class="kpi-label">有效订单</div><div class="kpi-value">{{ number_format($orderCount) }}</div><div class="kpi-foot">已付款/已发货/已完成</div></div>
        <div class="kpi kpi-accent"><div class="kpi-label">营收（CNY）</div><div class="kpi-value">¥{{ number_format($totalRevenue, 2) }}</div></div>
        <div class="kpi kpi-accent amber"><div class="kpi-label">总成本（CNY）</div><div class="kpi-value">¥{{ number_format($totalCost, 2) }}</div></div>
        <div class="kpi kpi-accent green"><div class="kpi-label">毛利（CNY）</div><div class="kpi-value">¥{{ number_format($totalProfit, 2) }}</div></div>
        <div class="kpi kpi-accent green"><div class="kpi-label">毛利率</div><div class="kpi-value">{{ $margin }}<span style="font-size:16px">%</span></div></div>
    </div>

    <div class="grid grid-2">
        {{-- 成本结构 --}}
        <div class="card">
            <div class="card-head"><h2 class="card-title">成本结构</h2><span class="muted" style="font-size:12px">看钱花在哪</span></div>
            <div class="card-body">
                @php
                    $costMax = max(1, $totalCost);
                    $costColors = ['商品采购' => '#2f6fed', '物流' => '#12a06a', '广告' => '#d98600', '其他' => '#7a879e'];
                @endphp
                @foreach ($costBreakdown as $label => $amount)
                    <div style="margin-bottom:14px">
                        <div class="row-between" style="margin-bottom:5px">
                            <span>{{ $label }}</span>
                            <span class="mono">¥{{ number_format($amount, 2) }}
                                <span class="muted">（{{ $totalCost > 0 ? round($amount / $totalCost * 100, 1) : 0 }}%）</span>
                            </span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ $amount > 0 ? max(2, round($amount / $costMax * 100)) : 0 }}%; background: {{ $costColors[$label] }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 按日趋势 --}}
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">按日营收 / 毛利</h2>
                <div class="legend"><span><i style="background:#2f6fed"></i>营收</span><span><i style="background:#12a06a"></i>毛利</span></div>
            </div>
            <div class="card-body">
                @if (count($byDay) > 31)
                    <div class="muted" style="font-size:12px">区间较长，仅展示营收数值</div>
                @endif
                <div class="trend" style="height:150px;overflow-x:auto">
                    @foreach ($byDay as $d)
                        <div class="trend-col" style="min-width:26px">
                            <div class="trend-bars">
                                <div class="trend-bar rev" style="height: {{ round($d['revenue'] / $maxDay * 100) }}%" title="营收 ¥{{ number_format($d['revenue'], 2) }}"></div>
                                <div class="trend-bar pro" style="height: {{ round(max(0, $d['profit']) / $maxDay * 100) }}%" title="毛利 ¥{{ number_format($d['profit'], 2) }}"></div>
                            </div>
                            <div class="trend-label">{{ $d['date'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- 平台 / 店铺 --}}
    <div class="grid grid-2" style="margin-top:18px">
        <div class="card">
            <div class="card-head"><h2 class="card-title">分平台表现</h2></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>平台</th><th class="num">订单</th><th class="num">营收</th><th class="num">毛利</th><th class="num">毛利率</th></tr></thead>
                        <tbody>
                        @forelse ($byPlatform as $p)
                            <tr>
                                <td><span class="chip"><span class="dot" style="background:{{ $p['color'] }}"></span>{{ $p['name'] }}</span></td>
                                <td class="num">{{ $p['orders'] }}</td>
                                <td class="num">¥{{ number_format($p['revenue'], 2) }}</td>
                                <td class="num strong {{ $p['profit'] >= 0 ? 'up' : 'down' }}">¥{{ number_format($p['profit'], 2) }}</td>
                                <td class="num">{{ $p['margin'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">区间内无数据</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">分店铺表现</h2></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>店铺</th><th class="num">订单</th><th class="num">营收</th><th class="num">毛利</th><th class="num">毛利率</th></tr></thead>
                        <tbody>
                        @forelse ($byShop as $s)
                            <tr>
                                <td>{{ $s['name'] }}</td>
                                <td class="num">{{ $s['orders'] }}</td>
                                <td class="num">¥{{ number_format($s['revenue'], 2) }}</td>
                                <td class="num strong {{ $s['profit'] >= 0 ? 'up' : 'down' }}">¥{{ number_format($s['profit'], 2) }}</td>
                                <td class="num">{{ $s['margin'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">区间内无数据</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- 亏损预警 + SKU Top --}}
    <div class="grid grid-2" style="margin-top:18px">
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">亏损订单预警</h2>
                <span class="badge badge-danger">{{ $lossOrders->count() }} 单</span>
            </div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>订单号</th><th>平台</th><th class="num">营收</th><th class="num">毛利</th></tr></thead>
                        <tbody>
                        @forelse ($lossOrders as $row)
                            <tr>
                                <td><a class="mono" href="{{ route('orders.show', $row['order']) }}">{{ $row['order']->order_no }}</a></td>
                                <td>{{ $row['order']->platform?->name }}</td>
                                <td class="num">¥{{ number_format($row['profit']['revenue'], 2) }}</td>
                                <td class="num strong down">¥{{ number_format($row['profit']['profit'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">没有亏损订单，健康 👍</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">SKU 毛利贡献 Top 10</h2></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>SKU</th><th>商品</th><th class="num">销量</th><th class="num">毛利</th><th class="num">毛利率</th></tr></thead>
                        <tbody>
                        @forelse ($skuTop as $s)
                            <tr>
                                <td class="mono">{{ $s['sku'] }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($s['name'], 18) }}</td>
                                <td class="num">{{ $s['qty'] }}</td>
                                <td class="num strong {{ $s['profit'] >= 0 ? 'up' : 'down' }}">¥{{ number_format($s['profit'], 2) }}</td>
                                <td class="num">{{ $s['margin'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">区间内无数据</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
