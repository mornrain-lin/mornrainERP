@extends('layouts.app')

@section('title', '商品 / SKU')
@section('desc', '采购成本是利润核算的输入项，务必维护准确')

@section('actions')
    <a class="btn btn-primary" href="{{ route('products.create') }}">＋ 新增商品</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('products.index') }}" class="filter-bar">
                <div class="field" style="min-width:220px">
                    <label>搜索</label>
                    <input type="text" name="q" value="{{ $q }}" placeholder="SKU / 名称 / 品类">
                </div>
                <div class="field" style="flex-direction:row;gap:8px">
                    <button class="btn btn-primary" type="submit">搜索</button>
                    <a class="btn" href="{{ route('products.index') }}">重置</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card-head">
            <h2 class="card-title">商品列表</h2>
            <span class="muted" style="font-size:12px">共 {{ $products->total() }} 个 SKU</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr><th>SKU</th><th>商品名称</th><th>品类</th><th class="num">采购成本(CNY)</th><th class="num">重量(g)</th><th>状态</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse ($products as $p)
                        <tr>
                            <td class="mono strong">{{ $p->sku }}</td>
                            <td>{{ $p->name }}</td>
                            <td>{{ $p->category ?? '—' }}</td>
                            <td class="num strong">¥{{ number_format($p->cost_price, 2) }}</td>
                            <td class="num">{{ number_format($p->weight_g, 0) }}</td>
                            <td>@if ($p->is_active)<span class="badge badge-success">在售</span>@else<span class="badge badge-muted">停用</span>@endif</td>
                            <td class="nowrap">
                                <a class="btn btn-sm" href="{{ route('products.edit', $p) }}">编辑</a>
                                <form method="post" action="{{ route('products.destroy', $p) }}" style="display:inline"
                                      onsubmit="return confirm('确认删除 SKU {{ $p->sku }}？')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><div class="big">▣</div>还没有商品</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($products->hasPages())
            <div class="pagination-wrap">{{ $products->links() }}</div>
        @endif
    </div>
@endsection
