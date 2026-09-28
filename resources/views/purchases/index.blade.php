@extends('layouts.app')

@section('title', '采购管理')
@section('desc', '采购单草稿 → 已下单 → 已入库（回写库存）')

@section('actions')
    <form method="post" action="{{ route('purchases.from-suggestions') }}">
        @csrf
        <button class="btn" type="submit" title="根据库存采购建议生成草稿采购单">⇪ 按采购建议生成</button>
    </form>
    <a class="btn btn-primary" href="{{ route('purchases.create') }}">＋ 新建采购单</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">采购单列表</h2><span class="muted" style="font-size:12px">共 {{ number_format($orders->total()) }} 张</span></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>采购单号</th><th>供应商</th><th>状态</th>
                        <th class="num">SKU 数</th><th class="num">金额(CNY)</th>
                        <th>下单日期</th><th>入库日期</th><th>创建人</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($orders as $po)
                        <tr>
                            <td class="mono strong">{{ $po->po_no }}</td>
                            <td>{{ $po->supplier?->name ?? '—' }}</td>
                            <td><span class="badge badge-{{ $po->status->tone() }}">{{ $po->status->label() }}</span></td>
                            <td class="num">{{ $po->items_count }}</td>
                            <td class="num">¥{{ number_format($po->total_amount, 2) }}</td>
                            <td class="muted">{{ $po->order_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="muted">{{ $po->received_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="muted">{{ $po->creator?->name ?? '—' }}</td>
                            <td class="nowrap"><a class="btn btn-sm" href="{{ route('purchases.show', $po) }}">详情</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty"><div class="big">▣</div>还没有采购单，点右上角「新建采购单」或「按采购建议生成」</div></td></tr>
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
