@extends('layouts.app')

@section('title', $supplier->exists ? '编辑供应商' : '新增供应商')
@section('desc', '停用后不会出现在采购单的新建下拉中，历史单据不受影响')

@section('actions')
    <a class="btn" href="{{ route('suppliers.index') }}">← 返回列表</a>
@endsection

@section('content')
<form method="post" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}">
    @csrf
    @if ($supplier->exists) @method('PUT') @endif

    <div class="card">
        <div class="card-head"><h2 class="card-title">基本信息</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label>供应商名称 *</label>
                    <input type="text" name="name" value="{{ old('name', $supplier->name) }}" placeholder="义乌优品供应链" required>
                </div>
                <div class="field">
                    <label>联系人</label>
                    <input type="text" name="contact" value="{{ old('contact', $supplier->contact) }}" placeholder="张经理">
                </div>
                <div class="field">
                    <label>电话</label>
                    <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" placeholder="13800000000">
                </div>
                <div class="field">
                    <label>邮箱</label>
                    <input type="email" name="email" value="{{ old('email', $supplier->email) }}" placeholder="supply@example.com">
                </div>
                <div class="field" style="grid-column:1/-1">
                    <label>地址</label>
                    <input type="text" name="address" value="{{ old('address', $supplier->address) }}" placeholder="浙江省义乌市…">
                </div>
                <div class="field" style="grid-column:1/-1">
                    <label>备注</label>
                    <textarea name="remark" rows="2" placeholder="账期、起订量、合作注意事项…">{{ old('remark', $supplier->remark) }}</textarea>
                </div>
            </div>

            <label style="display:flex;align-items:center;gap:8px;margin-top:14px;font-size:13px">
                <input type="checkbox" name="is_active" value="1" style="width:auto"
                       @checked(old('is_active', $supplier->exists ? $supplier->is_active : true))>
                启用（停用后不再出现在采购单新建下拉）
            </label>
        </div>
    </div>

    <div class="btn-row" style="margin-top:18px">
        <button class="btn btn-primary" type="submit">{{ $supplier->exists ? '保存修改' : '创建供应商' }}</button>
        <a class="btn" href="{{ route('suppliers.index') }}">取消</a>
    </div>
</form>
@endsection
