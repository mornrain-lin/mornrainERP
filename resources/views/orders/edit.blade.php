@extends('layouts.app')

@section('title', '编辑订单 ' . $order->order_no)
@section('desc', '调整金额、成本与备注（商品明细请通过重新导入或数据库维护）')

@section('actions')
    <a class="btn" href="{{ route('orders.show', $order) }}">← 返回详情</a>
@endsection

@section('content')
<form method="post" action="{{ route('orders.update', $order) }}">
    @csrf @method('PUT')

    <div class="card">
        <div class="card-head"><h2 class="card-title">基础信息</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field"><label>订单号</label><input type="text" value="{{ $order->order_no }}" disabled></div>
                <div class="field"><label>平台</label><input type="text" value="{{ $order->platform?->name }}" disabled></div>
                <div class="field"><label>店铺</label><input type="text" value="{{ $order->shop?->name }}" disabled></div>
                <div class="field"><label>当前状态</label><input type="text" value="{{ $order->status->label() }}" disabled></div>
                <div class="field">
                    <label>买家</label>
                    <input type="text" name="buyer_name" value="{{ old('buyer_name', $order->buyer_name) }}">
                </div>
                <div class="field">
                    <label>买家国家</label>
                    <input type="text" name="buyer_country" value="{{ old('buyer_country', $order->buyer_country) }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">金额与成本（{{ $order->currency }} / CNY）</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field"><label>商品金额</label><input type="number" step="0.01" name="goods_amount" value="{{ old('goods_amount', $order->goods_amount) }}" required></div>
                <div class="field"><label>运费收入</label><input type="number" step="0.01" name="shipping_income" value="{{ old('shipping_income', $order->shipping_income) }}"></div>
                <div class="field"><label>平台补贴 / 优惠</label><input type="number" step="0.01" name="discount_amount" value="{{ old('discount_amount', $order->discount_amount) }}"></div>
                <div class="field"><label>平台佣金</label><input type="number" step="0.01" name="platform_commission" value="{{ old('platform_commission', $order->platform_commission) }}"></div>
                <div class="field"><label>支付手续费</label><input type="number" step="0.01" name="payment_fee" value="{{ old('payment_fee', $order->payment_fee) }}"></div>
                <div class="field"><label>退款金额</label><input type="number" step="0.01" name="refund_amount" value="{{ old('refund_amount', $order->refund_amount) }}"></div>
                <div class="field"><label>物流成本(CNY)</label><input type="number" step="0.01" name="shipping_cost" value="{{ old('shipping_cost', $order->shipping_cost) }}"></div>
                <div class="field"><label>广告分摊(CNY)</label><input type="number" step="0.01" name="ad_cost" value="{{ old('ad_cost', $order->ad_cost) }}"></div>
                <div class="field"><label>其他成本(CNY)</label><input type="number" step="0.01" name="other_cost" value="{{ old('other_cost', $order->other_cost) }}"></div>
            </div>
            <div class="field" style="margin-top:14px">
                <label>备注</label>
                <textarea name="remark" rows="3">{{ old('remark', $order->remark) }}</textarea>
            </div>
        </div>
    </div>

    <div class="btn-row" style="margin-top:18px">
        <button class="btn btn-primary" type="submit">保存修改</button>
        <a class="btn" href="{{ route('orders.show', $order) }}">取消</a>
    </div>
</form>
@endsection
