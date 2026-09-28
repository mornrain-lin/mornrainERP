@extends('layouts.app')

@section('title', '账号管理')
@section('desc', '两级角色：管理员可改基础资料与对接配置，运营负责日常订单处理')

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">账号列表</h2>
            <span class="muted" style="font-size:12px">共 {{ $users->total() }} 个账号</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>姓名</th><th>登录邮箱</th><th>角色</th><th>最近登录</th>
                        <th>状态</th><th class="nowrap">重置密码</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($users as $u)
                        <tr>
                            <td class="strong">
                                {{ $u->name }}
                                @if ($u->id === auth()->id())<span class="badge badge-info">当前</span>@endif
                            </td>
                            <td class="mono">{{ $u->email }}</td>
                            <td>
                                @if ($u->isAdmin())
                                    <span class="badge badge-primary">管理员</span>
                                @else
                                    <span class="badge badge-muted">运营</span>
                                @endif
                            </td>
                            <td class="nowrap muted">{{ $u->last_login_at?->format('Y-m-d H:i') ?? '从未登录' }}</td>
                            <td>
                                @if ($u->is_active)
                                    <span class="badge badge-success">启用</span>
                                @else
                                    <span class="badge badge-muted">停用</span>
                                @endif
                            </td>
                            <td>
                                <form method="post" action="{{ route('users.password', $u) }}" class="inline-form">
                                    @csrf @method('PUT')
                                    <input type="password" name="password" placeholder="新密码" style="width:130px" required>
                                    <button class="btn btn-sm" type="submit" onclick="return confirm('确认重置 {{ $u->name }} 的密码？')">重置</button>
                                </form>
                            </td>
                            <td class="nowrap">
                                <form method="post" action="{{ route('users.toggle', $u) }}" style="display:inline">
                                    @csrf
                                    <button class="btn btn-sm" type="submit">{{ $u->is_active ? '停用' : '启用' }}</button>
                                </form>
                                @if ($u->id !== auth()->id())
                                    <form method="post" action="{{ route('users.destroy', $u) }}" style="display:inline"
                                          onsubmit="return confirm('确认删除账号 {{ $u->name }}？')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><div class="big">☺</div>还没有账号</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($users->hasPages())
            <div class="pagination-wrap">{{ $users->links() }}</div>
        @endif
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">新增账号</h2><span class="muted" style="font-size:12px">初始密码请通过其它渠道告知对方</span></div>
        <div class="card-body">
            <form method="post" action="{{ route('users.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label for="name">姓名</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="field">
                        <label for="email">登录邮箱</label>
                        <input type="text" id="email" name="email" required>
                    </div>
                    <div class="field">
                        <label for="password">初始密码</label>
                        <input type="text" id="password" name="password" required>
                        <div class="hint">至少 8 位，含大小写字母与数字</div>
                    </div>
                    <div class="field">
                        <label for="role">角色</label>
                        <select id="role" name="role">
                            @foreach (\App\Models\User::roles() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="btn-row" style="margin-top:16px">
                    <button class="btn btn-primary" type="submit">创建账号</button>
                </div>
            </form>
        </div>
    </div>
@endsection
