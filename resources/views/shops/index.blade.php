@extends('layouts.app')

@section('title', '店铺管理')
@section('desc', '一个平台可挂多个店铺/站点，是多店铺 ERP 的基本单元')

@section('actions')
    <a class="btn btn-primary" href="{{ route('shops.create') }}">＋ 新增店铺</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">店铺列表</h2>
            <span class="muted" style="font-size:12px">共 {{ $shops->total() }} 个店铺</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>平台</th><th>店铺</th><th>站点</th><th>币种 / 汇率</th>
                        <th class="num">订单数</th><th>状态</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($shops as $s)
                        <tr>
                            <td><span class="chip"><span class="dot" style="background:{{ $s->platform?->color }}"></span>{{ $s->platform?->name }}</span></td>
                            <td class="strong">{{ $s->name }}<div class="muted" style="font-size:11px">{{ $s->seller_account }}</div></td>
                            <td>{{ $s->region ?? '—' }}</td>
                            <td class="mono">{{ $s->currency }} · {{ $s->exchange_rate }}</td>
                            <td class="num">
                                <a href="{{ route('orders.index', ['shop_id' => $s->id]) }}">{{ number_format($s->orders_count) }}</a>
                            </td>
                            <td>
                                @if ($s->is_active)
                                    <span class="badge badge-success">启用</span>
                                @else
                                    <span class="badge badge-muted">停用</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                <a class="btn btn-sm" href="{{ route('shops.edit', $s) }}">编辑</a>
                                <form method="post" action="{{ route('shops.destroy', $s) }}" style="display:inline"
                                      onsubmit="return confirm('确认删除店铺 {{ $s->name }}？')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><div class="big">⛬</div>还没有店铺</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($shops->hasPages())
            <div class="pagination-wrap">{{ $shops->links() }}</div>
        @endif
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">已接入平台</h2><span class="muted" style="font-size:12px">系统预置 20+ 主流跨境平台</span></div>
        <div class="card-body">
            <div style="display:flex;flex-wrap:wrap;gap:8px">
                @foreach ($platforms as $p)
                    <span class="chip">
                        <span class="dot" style="background:{{ $p->color }}"></span>
                        {{ $p->name }}
                        <span class="muted">· 佣金 {{ $p->commission_rate }}% / 手续费 {{ $p->payment_fee_rate }}%</span>
                    </span>
                @endforeach
            </div>
        </div>
    </div>
@endsection
