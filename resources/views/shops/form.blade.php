@extends('layouts.app')

@section('title', $shop->exists ? '编辑店铺' : '新增店铺')
@section('desc', '汇率用于把平台结算币种换算成人民币，直接影响利润核算')

@section('actions')
    <a class="btn" href="{{ route('shops.index') }}">← 返回列表</a>
@endsection

@section('content')
<form method="post" action="{{ $shop->exists ? route('shops.update', $shop) : route('shops.store') }}">
    @csrf
    @if ($shop->exists) @method('PUT') @endif

    <div class="card">
        <div class="card-head"><h2 class="card-title">店铺信息</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label>平台 *</label>
                    <select name="platform_id" required>
                        @foreach ($platforms as $p)
                            <option value="{{ $p->id }}" @selected(old('platform_id', $shop->platform_id) == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>店铺名称 *</label>
                    <input type="text" name="name" value="{{ old('name', $shop->name) }}" placeholder="晨雨官方店" required>
                </div>
                <div class="field">
                    <label>卖家账号</label>
                    <input type="text" name="seller_account" value="{{ old('seller_account', $shop->seller_account) }}">
                </div>
                <div class="field">
                    <label>站点</label>
                    <input type="text" name="region" value="{{ old('region', $shop->region) }}" placeholder="MY / TH / SG / US">
                </div>
                <div class="field">
                    <label>币种 *</label>
                    <input type="text" name="currency" value="{{ old('currency', $shop->currency) }}" placeholder="MYR" required>
                </div>
                <div class="field">
                    <label>汇率（对 CNY）*</label>
                    <input type="number" step="0.000001" name="exchange_rate" value="{{ old('exchange_rate', $shop->exchange_rate) }}" required>
                </div>
            </div>

            <div class="field" style="margin-top:14px">
                <label>备注</label>
                <textarea name="remark" rows="2">{{ old('remark', $shop->remark) }}</textarea>
            </div>

            <label style="display:flex;align-items:center;gap:8px;margin-top:14px;font-size:13px">
                <input type="checkbox" name="is_active" value="1" style="width:auto" @checked(old('is_active', $shop->is_active))>
                启用该店铺
            </label>
        </div>
    </div>

    <div class="btn-row" style="margin-top:18px">
        <button class="btn btn-primary" type="submit">{{ $shop->exists ? '保存修改' : '创建店铺' }}</button>
        <a class="btn" href="{{ route('shops.index') }}">取消</a>
    </div>
</form>
@endsection
