@extends('layouts.app')

@section('title', '采购单 ' . $purchaseOrder->po_no)
@section('desc', ($purchaseOrder->supplier?->name ?? '—') . ' · ' . $purchaseOrder->status->label())

@php
    $st = $purchaseOrder->status;
    $editable = in_array($st, [\App\Enums\POStatus::Draft, \App\Enums\POStatus::Ordered], true);
@endphp

@section('actions')
    @if ($editable)
        <a class="btn" href="{{ route('purchases.edit', $purchaseOrder) }}">编辑</a>
    @endif
    @if ($st === \App\Enums\POStatus::Draft)
        <form method="post" action="{{ route('purchases.place', $purchaseOrder) }}" style="display:inline">
            @csrf
            <button class="btn" type="submit">标记已下单</button>
        </form>
    @endif
    @if ($editable)
        <form method="post" action="{{ route('purchases.cancel', $purchaseOrder) }}" style="display:inline"
              onsubmit="return confirm('确认取消采购单 {{ $purchaseOrder->po_no }}？')">
            @csrf
            <button class="btn btn-danger" type="submit">取消</button>
        </form>
    @endif
    @if (in_array($st, [\App\Enums\POStatus::Draft, \App\Enums\POStatus::Cancelled], true))
        <form method="post" action="{{ route('purchases.destroy', $purchaseOrder) }}" style="display:inline"
              onsubmit="return confirm('确认删除采购单 {{ $purchaseOrder->po_no }}？该操作不可恢复。')">
            @csrf @method('DELETE')
            <button class="btn btn-danger" type="submit">删除</button>
        </form>
    @endif
    <a class="btn" href="{{ route('purchases.index') }}">← 返回列表</a>
@endsection

@section('content')
    <div class="grid grid-2">
        <div>
            <div class="card">
                <div class="card-head">
                    <h2 class="card-title">采购单信息</h2>
                    <span class="badge badge-{{ $purchaseOrder->status->tone() }}">{{ $purchaseOrder->status->label() }}</span>
                </div>
                <div class="card-body">
                    <div class="detail-grid">
                        <div class="detail-item"><div class="k">采购单号</div><div class="v mono">{{ $purchaseOrder->po_no }}</div></div>
                        <div class="detail-item"><div class="k">供应商</div><div class="v">{{ $purchaseOrder->supplier?->name }}</div></div>
                        <div class="detail-item"><div class="k">下单日期</div><div class="v">{{ $purchaseOrder->order_date?->format('Y-m-d') ?? '—' }}</div></div>
                        <div class="detail-item"><div class="k">预计到货</div><div class="v">{{ $purchaseOrder->expected_at?->format('Y-m-d') ?? '—' }}</div></div>
                        <div class="detail-item"><div class="k">入库日期</div><div class="v">{{ $purchaseOrder->received_at?->format('Y-m-d') ?? '—' }}</div></div>
                        <div class="detail-item"><div class="k">创建人</div><div class="v">{{ $purchaseOrder->creator?->name ?? '—' }}</div></div>
                        <div class="detail-item"><div class="k">总金额</div><div class="v strong">¥{{ number_format($purchaseOrder->total_amount, 2) }}</div></div>
                    </div>
                    @if ($purchaseOrder->remark)
                        <div style="margin-top:12px"><span class="muted">备注：</span>{{ $purchaseOrder->remark }}</div>
                    @endif
                </div>
            </div>

            @if ($purchaseOrder->status->canReceive())
                <div class="card">
                    <div class="card-head"><h2 class="card-title">收货入库</h2></div>
                    <div class="card-body">
                        <p class="muted" style="font-size:13px;margin-top:0">确认收货后，将按明细数量回写各 SKU 库存（生成「采购入库」流水），状态置为「已入库」。</p>
                        <form method="post" action="{{ route('purchases.receive', $purchaseOrder) }}" class="form-grid" onsubmit="return confirm('确认收货并回写库存？该操作不可自动撤销。')">
                            @csrf
                            <div class="field">
                                <label>实际入库日期</label>
                                <input type="date" name="received_at" value="{{ now()->toDateString() }}">
                            </div>
                            <div class="field" style="justify-content:flex-end">
                                <button class="btn btn-primary" type="submit">确认收货入库</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div>
            <div class="card">
                <div class="card-head"><h2 class="card-title">采购明细（{{ $purchaseOrder->items->count() }} 项）</h2></div>
                <div class="card-body tight">
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead>
                            <tr><th>SKU</th><th>商品</th><th class="num">数量</th><th class="num">单价</th><th class="num">行小计</th><th class="num">已入</th></tr>
                            </thead>
                            <tbody>
                            @forelse ($purchaseOrder->items as $it)
                                <tr>
                                    <td class="mono">{{ $it->sku }}</td>
                                    <td>{{ $it->product_name }}</td>
                                    <td class="num">{{ $it->quantity }}</td>
                                    <td class="num">¥{{ number_format($it->unit_cost, 2) }}</td>
                                    <td class="num strong">¥{{ number_format($it->line_total, 2) }}</td>
                                    <td class="num">{{ $it->received_qty }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty">没有明细</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
