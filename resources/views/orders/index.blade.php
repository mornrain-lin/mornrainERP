@extends('layouts.app')

@section('title', '订单管理')
@section('desc', '多平台订单归集 · 状态流转 · 利润实时可见')

@section('actions')
    <a class="btn" href="{{ route('orders.export', request()->query()) }}">↓ 导出 CSV</a>
    <a class="btn" href="{{ route('orders.import.form') }}">⇪ 导入</a>
    <a class="btn btn-primary" href="{{ route('orders.create') }}">＋ 新建订单</a>
@endsection

@section('content')
    {{-- 筛选 --}}
    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('orders.index') }}" class="filter-bar">
                <div class="field">
                    <label>平台</label>
                    <select name="platform_id">
                        <option value="">全部平台</option>
                        @foreach ($platforms as $p)
                            <option value="{{ $p->id }}" @selected(($filters['platform_id'] ?? '') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>店铺</label>
                    <select name="shop_id">
                        <option value="">全部店铺</option>
                        @foreach ($shops as $s)
                            <option value="{{ $s->id }}" @selected(($filters['shop_id'] ?? '') == $s->id)>{{ $s->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>状态</label>
                    <select name="status">
                        <option value="">全部状态</option>
                        @foreach ($statuses as $s)
                            <option value="{{ $s['value'] }}" @selected(($filters['status'] ?? '') === $s['value'])>{{ $s['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="min-width:180px">
                    <label>关键词</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="订单号 / 买家 / SKU">
                </div>
                <div class="field">
                    <label>起始日期</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="field">
                    <label>结束日期</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="field" style="flex-direction:row;gap:8px">
                    <button class="btn btn-primary" type="submit">筛选</button>
                    <a class="btn" href="{{ route('orders.index') }}">重置</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card-head">
            <div>
                <h2 class="card-title">订单列表</h2>
                <div class="card-desc">
                    共 {{ number_format($orders->total()) }} 条 ·
                    本页营收 <span class="strong">¥{{ number_format($pageRevenue, 2) }}</span> ·
                    本页毛利 <span class="strong {{ $pageProfit >= 0 ? 'up' : 'down' }}">¥{{ number_format($pageProfit, 2) }}</span>
                </div>
            </div>
            <details>
                <summary class="btn btn-sm" style="cursor:pointer;list-style:none">⇧ 批量发货</summary>
                <div style="position:absolute;z-index:30;right:24px;margin-top:8px;width:380px" class="card">
                    <div class="card-body">
                        <form method="post" action="{{ route('orders.batch-ship') }}">
                            @csrf
                            <div class="field" style="margin-bottom:10px">
                                <label>物流商</label>
                                <select name="carrier">
                                    @foreach (\App\Models\Shipment::CARRIERS as $c)
                                        <option value="{{ $c }}">{{ $c }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:10px">
                                <label>逐行填写：订单号,运单号</label>
                                <textarea name="tracking_nos" rows="5" placeholder="SP-240101-0001,YT1234567890&#10;LZ-240101-0002,4PX99887766"></textarea>
                            </div>
                            <button class="btn btn-primary" type="submit">确认批量发货</button>
                        </form>
                    </div>
                </div>
            </details>
        </div>

        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>订单号</th>
                        <th>平台 / 店铺</th>
                        <th>买家</th>
                        <th class="num">件数</th>
                        <th class="num">商品金额</th>
                        <th class="num">营收(CNY)</th>
                        <th class="num">毛利(CNY)</th>
                        <th class="num">毛利率</th>
                        <th>状态</th>
                        <th>下单时间</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($orders as $o)
                        @php $p = $o->profit(); @endphp
                        <tr>
                            <td>
                                <a class="mono strong" href="{{ route('orders.show', $o) }}">{{ $o->order_no }}</a>
                                @if ($o->source !== 'manual')
                                    <div class="muted" style="font-size:11px">{{ $o->source === 'api' ? '平台同步' : 'CSV 导入' }}</div>
                                @endif
                            </td>
                            <td class="nowrap">
                                <span class="chip"><span class="dot" style="background:{{ $o->platform?->color }}"></span>{{ $o->platform?->name }}</span>
                                <div class="muted" style="font-size:11px">{{ $o->shop?->name }}{{ $o->shop?->region ? ' · ' . $o->shop->region : '' }}</div>
                            </td>
                            <td>{{ $o->buyer_name ?? '—' }}<div class="muted" style="font-size:11px">{{ $o->buyer_country }}</div></td>
                            <td class="num">{{ (int) ($o->total_quantity ?? 0) }}</td>
                            <td class="num">{{ $o->currency }} {{ number_format($o->goods_amount, 2) }}</td>
                            <td class="num">¥{{ number_format($p['revenue'], 2) }}</td>
                            <td class="num strong {{ $p['profit'] >= 0 ? 'up' : 'down' }}">¥{{ number_format($p['profit'], 2) }}</td>
                            <td class="num">{{ $p['margin'] }}%</td>
                            <td><span class="badge badge-{{ $o->status->tone() }}">{{ $o->status->label() }}</span></td>
                            <td class="muted nowrap">{{ $o->created_at->format('m-d H:i') }}</td>
                            <td class="nowrap">
                                <a class="btn btn-sm" href="{{ route('orders.show', $o) }}">详情</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <div class="empty">
                                    <div class="big">▤</div>
                                    没有符合条件的订单<br>
                                    <span style="font-size:12px">试试调整筛选条件，或点右上角「新建订单」</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($orders->hasPages())
            <div class="pagination-wrap">{{ $orders->links() }}</div>
        @endif
    </div>
@endsection
