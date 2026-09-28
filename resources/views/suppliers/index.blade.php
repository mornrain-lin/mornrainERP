@extends('layouts.app')

@section('title', '供应商')
@section('desc', '采购单的归属方，成本与交期的源头')

@section('actions')
    <a class="btn btn-primary" href="{{ route('suppliers.create') }}">＋ 新增供应商</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('suppliers.index') }}" class="filter-bar">
                <div class="field" style="min-width:220px">
                    <label>搜索</label>
                    <input type="text" name="q" value="{{ $q }}" placeholder="名称 / 联系人 / 电话 / 邮箱">
                </div>
                <div class="field" style="min-width:140px">
                    <label>状态</label>
                    <select name="status">
                        <option value="all" @selected($status === 'all')>全部</option>
                        <option value="active" @selected($status === 'active')>仅启用</option>
                        <option value="inactive" @selected($status === 'inactive')>仅停用</option>
                    </select>
                </div>
                <div class="field" style="flex-direction:row;gap:8px">
                    <button class="btn btn-primary" type="submit">搜索</button>
                    <a class="btn" href="{{ route('suppliers.index') }}">重置</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card-head">
            <h2 class="card-title">供应商列表</h2>
            <span class="muted" style="font-size:12px">共 {{ $suppliers->total() }} 家</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>供应商名称</th><th>联系人</th><th>电话</th><th>邮箱</th>
                        <th class="num">采购单</th><th>状态</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($suppliers as $s)
                        <tr>
                            <td class="strong">{{ $s->name }}</td>
                            <td>{{ $s->contact ?? '—' }}</td>
                            <td class="mono">{{ $s->phone ?? '—' }}</td>
                            <td>{{ $s->email ?? '—' }}</td>
                            <td class="num">
                                @if ($s->purchase_orders_count > 0)
                                    <span class="badge badge-muted">{{ $s->purchase_orders_count }} 张</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($s->is_active)
                                    <span class="badge badge-success">启用</span>
                                @else
                                    <span class="badge badge-muted">停用</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                <a class="btn btn-sm" href="{{ route('suppliers.edit', $s) }}">编辑</a>
                                <form method="post" action="{{ route('suppliers.toggle', $s) }}" style="display:inline">
                                    @csrf
                                    <button class="btn btn-sm" type="submit">{{ $s->is_active ? '停用' : '启用' }}</button>
                                </form>
                                <form method="post" action="{{ route('suppliers.destroy', $s) }}" style="display:inline"
                                      onsubmit="return confirm('确认删除供应商「{{ $s->name }}」？已关联采购单时将无法删除')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty">
                                    <div class="big">🏭</div>
                                    还没有供应商，<a href="{{ route('suppliers.create') }}">先新增一家</a> 才能创建采购单
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($suppliers->hasPages())
            <div class="pagination-wrap">{{ $suppliers->links() }}</div>
        @endif
    </div>
@endsection
