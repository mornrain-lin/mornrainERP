@extends('layouts.app')

@section('title', $product->exists ? '编辑商品' : '新增商品')
@section('desc', 'SKU 是订单明细与成本核算的关联键，建议与平台保持一致')

@section('actions')
    <a class="btn" href="{{ route('products.index') }}">← 返回列表</a>
@endsection

@section('content')
<form method="post" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    <div class="card">
        <div class="card-head"><h2 class="card-title">商品信息</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label>SKU *</label>
                    <input type="text" name="sku" value="{{ old('sku', $product->sku) }}" placeholder="MR-0001" required>
                </div>
                <div class="field">
                    <label>商品名称 *</label>
                    <input type="text" name="name" value="{{ old('name', $product->name) }}" required>
                </div>
                <div class="field">
                    <label>品类</label>
                    <input type="text" name="category" value="{{ old('category', $product->category) }}" placeholder="家居 / 户外 / 3C">
                </div>
                <div class="field">
                    <label>采购成本（CNY）*</label>
                    <input type="number" step="0.01" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}" required>
                </div>
                <div class="field">
                    <label>重量（克）</label>
                    <input type="number" step="0.01" name="weight_g" value="{{ old('weight_g', $product->weight_g) }}">
                </div>
            </div>

            <label style="display:flex;align-items:center;gap:8px;margin-top:14px;font-size:13px">
                <input type="checkbox" name="is_active" value="1" style="width:auto" @checked(old('is_active', $product->is_active))>
                在售 / 启用
            </label>
        </div>
    </div>

    <div class="btn-row" style="margin-top:18px">
        <button class="btn btn-primary" type="submit">{{ $product->exists ? '保存修改' : '创建商品' }}</button>
        <a class="btn" href="{{ route('products.index') }}">取消</a>
    </div>
</form>
@endsection
