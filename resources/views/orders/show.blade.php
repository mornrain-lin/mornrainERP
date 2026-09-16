@extends('layouts.app')

@section('title', '订单 ' . $order->order_no)
@section('desc', ($order->platform?->name ?? '—') . ' · ' . ($order->shop?->name ?? '—') . ' · ' . $order->created_at->format('Y-m-d H:i'))

@section('actions')
    <a class="btn" href="{{ route('orders.edit', $order) }}">编辑</a>
    <a class="btn" href="{{ route('orders.index') }}">← 返回列表</a>
@endsection

@section('content')
    <div class="grid grid-2">
        {{-- 左：订单信息 + 明细 --}}
        <div>
            <div class="card">
                <div class="card-head">
                    <h2 class="card-title">订单信息</h2>
                    <span class="badge badge-{{ $order->status->tone() }}">{{ $order->status->label() }}</span>
                </div>
                <div class="card-body">
                    <div class="detail-grid">
                        <div class="detail-item"><div class="k">订单号</div><div class="v mono">{{ $order->order_no }}</div></div>
                        <div class="detail-item"><div class="k">平台</div><div class="v">{{ $order->platform?->name }}</div></div>
                        <div class="detail-item"><div class="k">店铺</div><div class="v">{{ $order->shop?->display_name }}</div></div>
                        <div class="detail-item"><div class="k">买家</div><div class="v">{{ $order->buyer_name ?? '—' }} <span class="muted">{{ $order->buyer_country }}</span></div></div>
                        <div class="detail-item"><div class="k">币种 / 汇率</div><div class="v">{{ $order->currency }} · {{ $order->exchange_rate }}</div></div>
                        <div class="detail-item"><div class="k">来源</div><div class="v">{{ ['manual' => '手工创建', 'import' => 'CSV 导入', 'api' => '平台同步'][$order->source] ?? $order->source }}</div></div>
                        <div class="detail-item"><div class="k">付款时间</div><div class="v">{{ $order->paid_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
                        <div class="detail-item"><div class="k">发货时间</div><div class="v">{{ $order->shipped_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
                    </div>
                    @if ($order->remark)
                        <div style="margin-top:12px"><span class="muted">备注：</span>{{ $order->remark }}</div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2 class="card-title">商品明细（{{ $order->items->count() }} 项）</h2></div>
                <div class="card-body tight">
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead>
                            <tr>
                                <th>SKU</th><th>商品</th>
                                <th class="num">数量</th><th class="num">售价</th>
                                <th class="num">成本(CNY)</th><th class="num">行成本</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($order->items as $it)
                                <tr>
                                    <td class="mono">{{ $it->sku }}</td>
                                    <td>{{ $it->product_name }}</td>
                                    <td class="num">{{ $it->quantity }}</td>
                                    <td class="num">{{ $order->currency }} {{ number_format($it->unit_price, 2) }}</td>
                                    <td class="num">¥{{ number_format($it->unit_cost, 2) }}</td>
                                    <td class="num strong">¥{{ number_format($it->line_cost, 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- 物流 --}}
            <div class="card">
                <div class="card-head">
                    <h2 class="card-title">物流 / 发货</h2>
                    @if (in_array($order->status->value, ['paid', 'pending']))
                        <span class="muted" style="font-size:12px">可发货</span>
                    @endif
                </div>
                <div class="card-body">
                    @if ($order->shipments->isNotEmpty())
                        <div class="table-wrap" style="margin-bottom:14px">
                            <table class="tbl">
                                <thead><tr><th>物流商</th><th>运单号</th><th>状态</th><th class="num">运费</th><th>发货时间</th></tr></thead>
                                <tbody>
                                @foreach ($order->shipments as $sh)
                                    <tr>
                                        <td>{{ $sh->carrier }}</td>
                                        <td class="mono">{{ $sh->tracking_no }}</td>
                                        <td><span class="badge badge-{{ $sh->status->tone() }}">{{ $sh->status->label() }}</span></td>
                                        <td class="num">¥{{ number_format($sh->cost, 2) }}</td>
                                        <td class="muted nowrap">{{ $sh->shipped_at?->format('m-d H:i') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if (in_array($order->status->value, ['paid', 'pending']))
                        <form method="post" action="{{ route('orders.ship', $order) }}" class="form-grid">
                            @csrf
                            <div class="field">
                                <label>物流商</label>
                                <select name="carrier">
                                    @foreach ($carriers as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label>运单号</label>
                                <input type="text" name="tracking_no" placeholder="YT1234567890">
                            </div>
                            <div class="field">
                                <label>物流费用（CNY）</label>
                                <input type="number" step="0.01" min="0" name="cost" value="{{ $order->shipping_cost }}">
                            </div>
                            <div class="field" style="justify-content:flex-end">
                                <button class="btn btn-primary" type="submit">确认发货</button>
                            </div>
                        </form>
                    @elseif ($order->shipments->isEmpty())
                        <div class="muted">暂无物流记录</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- 右：利润核算 + 状态机 --}}
        <div>
            <div class="card">
                <div class="card-head"><h2 class="card-title">利润核算</h2></div>
                <div class="card-body">
                    <div class="profit-box">
                        <div class="profit-row">
                            <span>商品金额</span>
                            <span class="mono">{{ $order->currency }} {{ number_format($order->goods_amount, 2) }}</span>
                        </div>
                        <div class="profit-row">
                            <span>＋ 运费收入</span>
                            <span class="mono">{{ $order->currency }} {{ number_format($order->shipping_income, 2) }}</span>
                        </div>
                        <div class="profit-row">
                            <span>－ 平台佣金</span>
                            <span class="mono">{{ $order->currency }} {{ number_format($order->platform_commission, 2) }}</span>
                        </div>
                        <div class="profit-row">
                            <span>－ 支付手续费</span>
                            <span class="mono">{{ $order->currency }} {{ number_format($order->payment_fee, 2) }}</span>
                        </div>
                        <div class="profit-row">
                            <span>－ 平台补贴 / 优惠</span>
                            <span class="mono">{{ $order->currency }} {{ number_format($order->discount_amount, 2) }}</span>
                        </div>
                        <div class="profit-row">
                            <span>－ 退款</span>
                            <span class="mono">{{ $order->currency }} {{ number_format($order->refund_amount, 2) }}</span>
                        </div>
                        <div class="profit-row" style="border-top:1px solid #dbe7ff;margin-top:6px;padding-top:10px">
                            <span class="strong">平台净收入 × {{ $order->exchange_rate }} = 营收</span>
                            <span class="strong mono">¥{{ number_format($profit['revenue'], 2) }}</span>
                        </div>

                        <div style="height:12px"></div>

                        <div class="profit-row"><span>商品采购成本</span><span class="mono">¥{{ number_format($profit['product_cost'], 2) }}</span></div>
                        <div class="profit-row"><span>物流成本</span><span class="mono">¥{{ number_format($order->shipping_cost, 2) }}</span></div>
                        <div class="profit-row"><span>广告分摊</span><span class="mono">¥{{ number_format($order->ad_cost, 2) }}</span></div>
                        <div class="profit-row"><span>其他成本</span><span class="mono">¥{{ number_format($order->other_cost, 2) }}</span></div>

                        <div class="profit-row total">
                            <span>毛利</span>
                            <span class="{{ $profit['profit'] >= 0 ? 'up' : 'down' }}">
                                ¥{{ number_format($profit['profit'], 2) }}
                                <span style="font-size:13px;font-weight:600">（{{ $profit['margin'] }}%）</span>
                            </span>
                        </div>
                    </div>
                    <div class="muted" style="font-size:11px;margin-top:10px">
                        总成本 ¥{{ number_format($profit['cost'], 2) }} ·
                        净收扣减合计 {{ $order->currency }} {{ number_format($profit['deductions'], 2) }}
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2 class="card-title">状态流转</h2></div>
                <div class="card-body">
                    @if ($nextStates)
                        <form method="post" action="{{ route('orders.status', $order) }}">
                            @csrf
                            <div class="field" style="margin-bottom:12px">
                                <label>推进到</label>
                                <select name="status">
                                    @foreach ($nextStates as $ns)
                                        <option value="{{ $ns->value }}">{{ $ns->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:12px">
                                <label>附加备注（可选）</label>
                                <input type="text" name="remark" placeholder="例如：买家已确认收货">
                            </div>
                            <button class="btn btn-primary" type="submit">执行流转</button>
                        </form>
                    @else
                        <div class="muted">该订单已处于终态（{{ $order->status->label() }}），无法继续流转。</div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2 class="card-title">危险操作</h2></div>
                <div class="card-body">
                    <form method="post" action="{{ route('orders.destroy', $order) }}"
                          onsubmit="return confirm('确认删除订单 {{ $order->order_no }}？该操作不可恢复。')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger" type="submit">删除订单</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
