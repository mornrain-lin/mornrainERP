@extends('layouts.app')

@section('title', '库存管理')
@section('desc', '发货自动扣减、低于安全库存预警、按近 30 天销量给出补货建议')

@section('content')
    <div class="kpi-grid">
        <div class="kpi kpi-accent">
            <div class="kpi-label">SKU 总数</div>
            <div class="kpi-value">{{ $summary['sku_count'] }}</div>
            <div class="kpi-foot">含停用 SKU</div>
        </div>
        <div class="kpi kpi-accent green">
            <div class="kpi-label">库存货值</div>
            <div class="kpi-value">¥{{ number_format($summary['stock_value'], 2) }}</div>
            <div class="kpi-foot">按采购成本估算</div>
        </div>
        <div class="kpi kpi-accent amber">
            <div class="kpi-label">低于安全库存</div>
            <div class="kpi-value">{{ $summary['low_count'] }}</div>
            <div class="kpi-foot">需尽快补货</div>
        </div>
        <div class="kpi kpi-accent red">
            <div class="kpi-label">零库存 / 超卖</div>
            <div class="kpi-value">{{ $summary['out_count'] }}</div>
            <div class="kpi-foot">库存 ≤ 0 的 SKU</div>
        </div>
    </div>

    @if ($lowStock->isNotEmpty())
        <div class="alert alert-err">
            <span>!</span>
            <div>
                <span class="strong">{{ $lowStock->count() }} 个 SKU 已低于安全库存：</span>
                {{ $lowStock->take(6)->map(fn($p) => $p->sku . '（剩 ' . $p->stock . '）')->implode('、') }}
                @if ($lowStock->count() > 6) 等 @endif
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">库存明细</h2>
            <span class="muted" style="font-size:12px">按库存升序，缺货在前</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>SKU</th><th>商品</th><th class="num">库存</th><th class="num">安全库存</th>
                        <th class="num">累计销量</th><th class="num">货值</th><th>状态</th>
                        <th style="min-width:330px">库存调整</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($products as $p)
                        <tr>
                            <td class="mono">{{ $p->sku }}</td>
                            <td>
                                <span class="strong">{{ $p->name }}</span>
                                <div class="muted" style="font-size:11px">{{ $p->category ?? '未分类' }}</div>
                            </td>
                            <td class="num strong {{ $p->stock <= 0 ? 'up' : '' }}">{{ $p->stock }}</td>
                            <td class="num muted">{{ $p->safety_stock }}</td>
                            <td class="num">{{ number_format($p->sold_count ?? 0) }}</td>
                            <td class="num">¥{{ number_format(max(0, $p->stock) * $p->cost_price, 2) }}</td>
                            <td>
                                @if ($p->stock <= 0)
                                    <span class="badge badge-danger">缺货</span>
                                @elseif ($p->isLowStock())
                                    <span class="badge badge-warn">需补货</span>
                                @else
                                    <span class="badge badge-success">充足</span>
                                @endif
                            </td>
                            <td>
                                <form method="post" action="{{ route('inventory.adjust', $p) }}" class="inline-form">
                                    @csrf
                                    <select name="type" style="width:88px">
                                        <option value="in">入库 +</option>
                                        <option value="out">出库 −</option>
                                        <option value="adjust">盘点 =</option>
                                    </select>
                                    <input type="number" name="quantity" min="1" placeholder="数量" style="width:76px" required>
                                    <input type="text" name="remark" placeholder="备注" style="width:110px">
                                    <button class="btn btn-sm" type="submit">保存</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty"><div class="big">▣</div>还没有商品，请先到商品 / SKU 创建</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($products->hasPages())
            <div class="pagination-wrap">{{ $products->links() }}</div>
        @endif
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">采购建议</h2>
            <div style="display:flex;gap:8px;align-items:center">
                <span class="muted" style="font-size:12px">建议补货量 = 近 30 天销量 + 安全库存 − 当前库存</span>
                <form method="post" action="{{ route('purchases.from-suggestions') }}">
                    @csrf
                    <button class="btn btn-sm" type="submit" title="根据下方建议一键生成草稿采购单">生成采购单</button>
                </form>
            </div>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>SKU</th><th>商品</th><th class="num">近 30 天销量</th><th class="num">当前库存</th>
                        <th class="num">安全库存</th><th class="num">建议补货</th><th class="num">预估采购额</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($suggestions as $row)
                        <tr>
                            <td class="mono">{{ $row['product']->sku }}</td>
                            <td>{{ $row['product']->name }}</td>
                            <td class="num">{{ number_format($row['sold']) }}</td>
                            <td class="num">{{ $row['product']->stock }}</td>
                            <td class="num muted">{{ $row['product']->safety_stock }}</td>
                            <td class="num strong">{{ number_format($row['suggest']) }}</td>
                            <td class="num">¥{{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><div class="big">✓</div>暂无补货需求</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">最近库存流水</h2><span class="muted" style="font-size:12px">最近 15 条</span></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr><th>时间</th><th>SKU</th><th>类型</th><th class="num">变动</th><th class="num">变动后</th><th>操作人</th><th>说明</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($movements as $m)
                        <tr>
                            <td class="nowrap mono">{{ $m->created_at->format('m-d H:i') }}</td>
                            <td class="mono">{{ $m->product?->sku }}</td>
                            <td>
                                <span class="badge {{ $m->type === 'in' ? 'badge-success' : ($m->type === 'out' ? 'badge-info' : 'badge-muted') }}">
                                    {{ $m->typeLabel() }}
                                </span>
                            </td>
                            <td class="num {{ $m->quantity > 0 ? 'down' : 'up' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                            <td class="num">{{ $m->balance_after }}</td>
                            <td class="muted">{{ $m->user?->name ?? '系统' }}</td>
                            <td class="muted" style="font-size:12px">{{ $m->remark }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><div class="big">⇅</div>还没有库存变动记录</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
