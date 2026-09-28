@extends('layouts.app')

@php($editing = $po->exists)

@section('title', $editing ? '编辑采购单 · ' . $po->po_no : '新建采购单')
@section('desc', $editing
    ? '仅「草稿 / 已下单」可编辑；已入库或已取消的单据不可修改'
    : '录入供应商与采购明细，可保存为草稿或直接标记已下单')

@section('actions')
    <a class="btn" href="{{ route('purchases.index') }}">← 返回列表</a>
@endsection

@section('content')
<form method="post" action="{{ $editing ? route('purchases.update', $po) : route('purchases.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card">
        <div class="card-head"><h2 class="card-title">基础信息</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label>供应商 *</label>
                    <select name="supplier_id" required>
                        <option value="">选择供应商</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}" @selected(old('supplier_id', $po->supplier_id) == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                    @if ($suppliers->isEmpty())
                        <div class="hint" style="color:var(--danger)">
                            还没有启用中的供应商，请先 <a href="{{ route('suppliers.create') }}">新增供应商</a>
                        </div>
                    @endif
                </div>
                <div class="field">
                    <label>状态</label>
                    <select name="status">
                        <option value="draft" @selected(old('status', $editing ? $po->status->value : 'draft') === 'draft')>草稿</option>
                        <option value="ordered" @selected(old('status', $editing ? $po->status->value : '') === 'ordered')>已下单</option>
                    </select>
                    <div class="hint">收货入库后状态自动变为「已入库」</div>
                </div>
                <div class="field">
                    <label>下单日期</label>
                    <input type="date" name="order_date"
                           value="{{ old('order_date', $editing ? ($po->order_date?->toDateString() ?? '') : now()->toDateString()) }}">
                </div>
                <div class="field">
                    <label>预计到货</label>
                    <input type="date" name="expected_at"
                           value="{{ old('expected_at', $editing && $po->expected_at ? $po->expected_at->toDateString() : '') }}">
                </div>
            </div>
            <div class="field" style="margin-top:14px">
                <label>备注</label>
                <textarea name="remark" rows="2">{{ old('remark', $po->remark) }}</textarea>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card-head">
            <h2 class="card-title">采购明细</h2>
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
                        <th style="width:130px">采购单价(CNY)</th>
                        <th style="width:130px">行小计</th>
                        <th style="width:50px"></th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="hint" style="margin-top:10px">
                合计：<strong id="grand-total">¥0.00</strong>（保存时按明细行小计自动汇总）
            </div>
        </div>
    </div>

    <div class="btn-row" style="margin-top:18px">
        <button class="btn btn-primary" type="submit">{{ $editing ? '保存修改' : '保存采购单' }}</button>
        <a class="btn" href="{{ route('purchases.index') }}">取消</a>
    </div>
</form>

<script>
    const PRODUCTS = @json($products->map(fn ($p) => ['sku' => $p->sku, 'name' => $p->name, 'cost' => (float) $p->cost_price]));
    const INITIAL = @json($items);
    const tbody = document.querySelector('#items tbody');

    function productOptions(selected) {
        return PRODUCTS.map(p =>
            `<option value="${p.sku}" data-name="${p.name}" data-cost="${p.cost}" ${selected === p.sku ? 'selected' : ''}>${p.sku}</option>`
        ).join('');
    }

    function addRow(item) {
        item = item || {};
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><select name="items[][sku]" onchange="syncProduct(this)" style="width:100%">
                <option value="">— 选择 SKU —</option>${productOptions(item.sku || '')}
            </select></td>
            <td><input type="text" name="items[][product_name]" style="width:100%"></td>
            <td><input type="number" min="1" name="items[][quantity]" class="qty" oninput="recalc(this)" style="width:100%"></td>
            <td><input type="number" step="0.01" min="0" name="items[][unit_cost]" class="cost" oninput="recalc(this)" style="width:100%"></td>
            <td class="num line-total">¥0.00</td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove(); grandTotal()">×</button></td>
        `;
        tbody.appendChild(tr);

        tr.querySelector('[name="items[][product_name]"]').value = item.product_name || '';
        tr.querySelector('.qty').value = item.quantity ?? 1;
        tr.querySelector('.cost').value = item.unit_cost ?? 0;
        recalc(tr.querySelector('.cost'));
    }

    function syncProduct(sel) {
        const opt = sel.selectedOptions[0];
        if (!opt || !opt.value) return;
        const tr = sel.closest('tr');
        tr.querySelector('[name="items[][product_name]"]').value = opt.dataset.name || '';
        const cost = tr.querySelector('.cost');
        if (!cost.value) cost.value = opt.dataset.cost || 0;
        recalc(cost);
    }

    function recalc(el) {
        const tr = el.closest('tr');
        const q = parseFloat(tr.querySelector('.qty')?.value || 0);
        const c = parseFloat(tr.querySelector('.cost')?.value || 0);
        tr.querySelector('.line-total').textContent = '¥' + (q * c).toFixed(2);
        grandTotal();
    }

    function grandTotal() {
        let sum = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const t = tr.querySelector('.line-total')?.textContent || '0';
            sum += parseFloat(t.replace(/[^\d.-]/g, '')) || 0;
        });
        document.getElementById('grand-total').textContent = '¥' + sum.toFixed(2);
    }

    if (INITIAL.length) {
        INITIAL.forEach(addRow);
    } else {
        addRow();
    }
</script>
@endsection
