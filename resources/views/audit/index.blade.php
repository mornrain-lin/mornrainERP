@extends('layouts.app')

@section('title', '操作审计')
@section('desc', '记录关键业务动作：谁、何时、对什么、做了什么')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('audit.index') }}" class="filter-bar">
                <div class="field" style="min-width:220px">
                    <label>描述关键词</label>
                    <input type="text" name="q" value="{{ $q }}" placeholder="订单号 / 采购单号 / SKU">
                </div>
                <div class="field" style="min-width:170px">
                    <label>动作</label>
                    <select name="action">
                        <option value="all" @selected($action === 'all')>全部动作</option>
                        @foreach ($actions as $key => $label)
                            <option value="{{ $key }}" @selected($action === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="min-width:160px">
                    <label>操作人</label>
                    <select name="user_id">
                        <option value="">全部</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected((string) $userId === (string) $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="min-width:130px">
                    <label>时间范围</label>
                    <select name="days">
                        <option value="1" @selected($days === 1)>近 1 天</option>
                        <option value="7" @selected($days === 7)>近 7 天</option>
                        <option value="30" @selected($days === 30)>近 30 天</option>
                        <option value="0" @selected($days === 0)>全部</option>
                    </select>
                </div>
                <div class="field" style="flex-direction:row;gap:8px">
                    <button class="btn btn-primary" type="submit">搜索</button>
                    <a class="btn" href="{{ route('audit.index') }}">重置</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card-head">
            <h2 class="card-title">审计日志</h2>
            <span class="muted" style="font-size:12px">共 {{ number_format($logs->total()) }} 条</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th style="width:150px">时间</th>
                        <th style="width:120px">操作人</th>
                        <th style="width:130px">动作</th>
                        <th style="width:170px">对象</th>
                        <th>描述</th>
                        <th style="width:120px">IP</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="muted nowrap">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                @if ($log->user)
                                    {{ $log->user->name }}
                                @else
                                    <span class="muted">系统 / 未登录</span>
                                @endif
                            </td>
                            <td><span class="badge badge-muted">{{ $log->actionLabel() }}</span></td>
                            <td class="muted">{{ $log->subjectLabel() ?? '—' }}</td>
                            <td>{{ $log->description }}</td>
                            <td class="mono muted">{{ $log->ip ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty">
                                    <div class="big">🔍</div>
                                    暂无审计记录，操作订单 / 采购 / 库存后会出现在这里
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($logs->hasPages())
            <div class="pagination-wrap">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
