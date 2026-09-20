@extends('layouts.app')

@section('title', '经营概览')
@section('desc', '订单、营收与毛利的实时快照 — ' . now()->format('Y年m月d日 H:i'))

@section('actions')
    <a class="btn btn-primary" href="{{ route('orders.create') }}">＋ 新建订单</a>
@endsection

@section('content')
    @php
        $maxRev = max(1, collect($trend)->max('revenue'));
    @endphp

    {{-- KPI --}}
    <div class="kpi-grid">
        <div class="kpi kpi-accent">
            <div class="kpi-label">今日订单</div>
            <div class="kpi-value">{{ number_format($kpi['today_orders']) }}</div>
            <div class="kpi-foot">本月累计 {{ number_format($kpi['month_orders']) }} 单</div>
        </div>
        <div class="kpi kpi-accent green">
            <div class="kpi-label">今日营收（CNY）</div>
            <div class="kpi-value">¥{{ number_format($kpi['today_revenue'], 2) }}</div>
            <div class="kpi-foot">本月 ¥{{ number_format($kpi['month_revenue'], 2) }}</div>
        </div>
        <div class="kpi kpi-accent green">
            <div class="kpi-label">今日毛利（CNY）</div>
            <div class="kpi-value">¥{{ number_format($kpi['today_profit'], 2) }}</div>
            <div class="kpi-foot">本月 ¥{{ number_format($kpi['month_profit'], 2) }}</div>
        </div>
        <div class="kpi kpi-accent">
            <div class="kpi-label">本月毛利率</div>
            <div class="kpi-value">{{ $kpi['month_margin'] }}<span style="font-size:16px">%</span></div>
            <div class="kpi-foot">营收 ¥{{ number_format($kpi['month_revenue'], 0) }}</div>
        </div>
        <div class="kpi kpi-accent amber">
            <div class="kpi-label">待发货</div>
            <div class="kpi-value">{{ number_format($kpi['pending_ship']) }}</div>
            <div class="kpi-foot">需要尽快处理</div>
        </div>
        <div class="kpi kpi-accent red">
            <div class="kpi-label">退款中</div>
            <div class="kpi-value">{{ number_format($kpi['refunding']) }}</div>
            <div class="kpi-foot">关注资金与库存回滚</div>
        </div>
    </div>

    <div class="grid grid-2">
        {{-- 近 7 日趋势 --}}
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">近 7 日营收 / 毛利趋势</h2>
                    <div class="card-desc">柱高按当日营收缩放</div>
                </div>
                <div class="legend">
                    <span><i style="background:#2f6fed"></i>营收</span>
                    <span><i style="background:#12a06a"></i>毛利</span>
                </div>
            </div>
            <div class="card-body">
                <div class="trend">
                    @foreach ($trend as $t)
                        <div class="trend-col">
                            <div class="trend-bars">
                                <div class="trend-bar rev" style="height: {{ round($t['revenue'] / $maxRev * 100) }}%"
                                     title="营收 ¥{{ number_format($t['revenue'], 2) }}"></div>
                                <div class="trend-bar pro" style="height: {{ round(max(0, $t['profit']) / $maxRev * 100) }}%"
                                     title="毛利 ¥{{ number_format($t['profit'], 2) }}"></div>
                            </div>
                            <div class="trend-label">{{ $t['date'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- 平台分布 --}}
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">本月各平台营收占比</h2>
                    <div class="card-desc">按平台归集，方便看出主战场</div>
                </div>
            </div>
            <div class="card-body">
                @forelse ($platformStats as $ps)
                    <div style="margin-bottom:14px">
                        <div class="row-between" style="margin-bottom:5px">
                            <span class="chip"><span class="dot" style="background:{{ $ps['color'] }}"></span>{{ $ps['name'] }}</span>
                            <span class="mono">
                                {{ $ps['orders'] }} 单 · ¥{{ number_format($ps['revenue'], 2) }}
                                <span class="muted">/ 毛利 ¥{{ number_format($ps['profit'], 2) }}</span>
                            </span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ $ps['revenue'] > 0 ? max(2, round($ps['revenue'] / $platformMax * 100)) : 0 }}%; background: {{ $ps['color'] }}"></div>
                        </div>
                    </div>
                @empty
                    <div class="empty">暂无本月数据</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-top:18px">
        {{-- 待发货 --}}
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">待发货订单</h2>
                <a class="btn btn-sm" href="{{ route('orders.index', ['status' => 'paid']) }}">全部 →</a>
            </div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>订单号</th><th>平台/店铺</th><th class="num">金额</th><th>状态</th></tr></thead>
                        <tbody>
                        @forelse ($pendingOrders as $o)
                            <tr>
                                <td><a class="mono" href="{{ route('orders.show', $o) }}">{{ $o->order_no }}</a></td>
                                <td>{{ $o->platform?->name }}<span class="muted"> · {{ $o->shop?->name }}</span></td>
                                <td class="num">{{ $o->currency }} {{ number_format($o->goods_amount, 2) }}</td>
                                <td><span class="badge badge-{{ $o->status->tone() }}">{{ $o->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">没有待发货订单，很棒 👍</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- 最新订单 --}}
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">最新订单</h2>
                <a class="btn btn-sm" href="{{ route('orders.index') }}">全部 →</a>
            </div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>订单号</th><th>买家</th><th class="num">毛利</th><th class="num">毛利率</th></tr></thead>
                        <tbody>
                        @forelse ($latestOrders as $o)
                            @php $p = $o->profit(); @endphp
                            <tr>
                                <td><a class="mono" href="{{ route('orders.show', $o) }}">{{ $o->order_no }}</a></td>
                                <td>{{ $o->buyer_name ?? '—' }}<span class="muted"> · {{ $o->buyer_country }}</span></td>
                                <td class="num {{ $p['profit'] >= 0 ? 'up' : 'down' }}">¥{{ number_format($p['profit'], 2) }}</td>
                                <td class="num">{{ $p['margin'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">还没有订单，点右上角「＋ 新建订单」试试</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
