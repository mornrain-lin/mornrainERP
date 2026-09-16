@extends('layouts.app')

@section('title', '新建订单')
@section('desc', '手工录入订单，系统会按平台费率自动补全佣金与手续费')

@section('actions')
    <a class="btn" href="{{ route('orders.index') }}">← 返回列表</a>
@endsection

@section('content')
<form method="post" action="{{ route('orders.store') }}">
    @csrf

    <div class="card">
        <div class="card-head"><h2 class="card-title">基础信息</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label>订单号 *</label>
                    <input type="text" name="order_no" value="{{ old('order_no', 'MR-' . now()->format('ymd') . '-' . random_int(1000, 9999)) }}" required>
                </div>
                <div class="field">
                    <label>平台 *</label>
                    <select name="platform_id" id="platform_id" required>
                        @foreach ($platforms as $p)
                            <option value="{{ $p->id }}" data-rate="{{ $p->commission_rate }}" data-fee="{{ $p->payment_fee_rate }}" @selected(old('platform_id') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>店铺 *</label>
                    <select name="shop_id" id="shop_id" required>
                        @foreach ($shops as $s)
                            <option value="{{ $s->id }}" data-currency="{{ $s->currency }}" data-rate="{{ $s->exchange_rate }}" data-region="{{ $s->region }}" @selected(old('shop_id') == $s->id)>
                                {{ $s->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>状态 *</label>
                    <select name="status" required>
                        @foreach ($statuses as $s)
                            <option value="{{ $s['value'] }}" @selected(old('status', 'paid') === $s['value'])>{{ $s['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>买家</label>
                    <input type="text" name="buyer_name" value="{{ old('buyer_name') }}" placeholder="Ahmad Ali">
                </div>
                <div class="field">
                    <label>买家国家</label>
                    <input type="text" name="buyer_country" id="buyer_country" value="{{ old('buyer_country') }}" placeholder="MY">
                </div>
                <div class="field">
                    <label>币种</label>
                    <input type="text" name="currency" id="currency" value="{{ old('currency', 'MYR') }}">
                </div>
                <div class="field">
                    <label>汇率（对 CNY）</label>
                    <input type="number" step="0.000001" name="exchange_rate" id="exchange_rate" value="{{ old('exchange_rate', 1) }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">商品明细</h2>
            <button class="btn btn-sm" type="button" onclick="addRow()">＋ 添加一行</button>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table class="tbl" id="items">
                    <thead>
                    <tr>
                        <th style="min-width:150px">SKU</th>
                        <th style="min-width:170px">商品名称</th>
                        <th style="width:90px">数量</th>
                        <th style="width:120px">售价(平台币)</th>
                        <th style="width:120px">成本(CNY)</th>
                        <th style="width:50px"></th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">金额与成本</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label>商品金额 *</label>
                    <input type="number" step="0.01" name="goods_amount" id="goods_amount" value="{{ old('goods_amount', 0) }}" required>
                </div>
                <div class="field">
                    <label>运费收入</label>
                    <input type="number" step="0.01" name="shipping_income" value="{{ old('shipping_income', 0) }}">
                </div>
                <div class="field">
                    <label>平台补贴 / 优惠</label>
                    <input type="number" step="0.01" name="discount_amount" value="{{ old('discount_amount', 0) }}">
                </div>
                <div class="field">
                    <label>平台佣金（留空按费率自动算）</label>
                    <input type="number" step="0.01" name="platform_commission" id="platform_commission" value="{{ old('platform_commission') }}" placeholder="自动">
                </div>
                <div class="field">
                    <label>支付手续费（留空按费率自动算）</label>
                    <input type="number" step="0.01" name="payment_fee" id="payment_fee" value="{{ old('payment_fee') }}" placeholder="自动">
                </div>
                <div class="field">
                    <label>退款金额</label>
                    <input type="number" step="0.01" name="refund_amount" value="{{ old('refund_amount', 0) }}">
                </div>
                <div class="field">
                    <label>物流成本（CNY）</label>
                    <input type="number" step="0.01" name="shipping_cost" value="{{ old('shipping_cost', 0) }}">
                </div>
                <div class="field">
                    <label>广告分摊（CNY）</label>
                    <input type="number" step="0.01" name="ad_cost" value="{{ old('ad_cost', 0) }}">
                </div>
                <div class="field">
                    <label>其他成本（CNY）</label>
                    <input type="number" step="0.01" name="other_cost" value="{{ old('other_cost', 0) }}">
                </div>
            </div>
            <div class="field" style="margin-top:14px">
                <label>备注</label>
                <textarea name="remark" rows="2">{{ old('remark') }}</textarea>
            </div>
        </div>
    </div>

    <div class="btn-row" style="margin-top:18px">
        <button class="btn btn-primary" type="submit">保存订单</button>
        <a class="btn" href="{{ route('orders.index') }}">取消</a>
    </div>
</form>

<script>
    const PRODUCTS = @json($products->map(fn ($p) => ['sku' => $p->sku, 'name' => $p->name, 'cost' => (float) $p->cost_price]));
    const tbody = document.querySelector('#items tbody');

    function productOptions(selected) {
        return PRODUCTS.map(p =>
            `<option value="${p.sku}" data-name="${p.name}" data-cost="${p.cost}" ${selected === p.sku ? 'selected' : ''}>${p.sku}</option>`
        ).join('');
    }

    function addRow() {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><select name="items[][sku]" onchange="syncProduct(this)" style="width:100%">
                <option value="">— 选择 SKU —</option>${productOptions()}
            </select></td>
            <td><input type="text" name="items[][product_name]" style="width:100%"></td>
            <td><input type="number" min="1" value="1" name="items[][quantity]" style="width:100%"></td>
            <td><input type="number" step="0.01" min="0" value="0" name="items[][unit_price]" class="price" oninput="sumGoods()" style="width:100%"></td>
            <td><input type="number" step="0.01" min="0" value="0" name="items[][unit_cost]" style="width:100%"></td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();sumGoods()">×</button></td>
        `;
        tbody.appendChild(tr);
    }

    function syncProduct(sel) {
        const opt = sel.selectedOptions[0];
        if (!opt || !opt.value) return;
        const tr = sel.closest('tr');
        tr.querySelector('[name="items[][product_name]"]').value = opt.dataset.name || '';
        tr.querySelector('[name="items[][unit_cost]"]').value = opt.dataset.cost || 0;
    }

    function sumGoods() {
        let sum = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const q = parseFloat(tr.querySelector('[name="items[][quantity]"]')?.value || 0);
            const p = parseFloat(tr.querySelector('.price')?.value || 0);
            sum += q * p;
        });
        const box = document.getElementById('goods_amount');
        if (sum > 0) box.value = sum.toFixed(2);
        autoFee();
    }

    function autoFee() {
        const pSel = document.getElementById('platform_id');
        const opt = pSel.selectedOptions[0];
        const goods = parseFloat(document.getElementById('goods_amount').value || 0);
        const rate = parseFloat(opt?.dataset.rate || 0);
        const fee = parseFloat(opt?.dataset.fee || 0);
        const cBox = document.getElementById('platform_commission');
        const fBox = document.getElementById('payment_fee');
        if (!cBox.value) cBox.placeholder = (goods * rate / 100).toFixed(2);
        if (!fBox.value) fBox.placeholder = (goods * fee / 100).toFixed(2);
    }

    document.getElementById('platform_id').addEventListener('change', autoFee);
    document.getElementById('goods_amount').addEventListener('input', autoFee);

    document.getElementById('shop_id').addEventListener('change', function () {
        const opt = this.selectedOptions[0];
        if (!opt) return;
        if (opt.dataset.currency) document.getElementById('currency').value = opt.dataset.currency;
        if (opt.dataset.rate) document.getElementById('exchange_rate').value = opt.dataset.rate;
        if (opt.dataset.region) document.getElementById('buyer_country').value = opt.dataset.region;
    });

    addRow();
</script>
@endsection
