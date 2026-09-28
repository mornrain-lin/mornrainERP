@extends('layouts.app')

@section('title', '修改密码')
@section('desc', '定期更换密码，避免弱口令被撞库')

@section('content')
    <div class="card" style="max-width:520px">
        <div class="card-head"><h2 class="card-title">修改登录密码</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('password.update') }}">
                @csrf
                @method('PUT')

                <div class="field">
                    <label for="current_password">当前密码</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                </div>
                <div class="field">
                    <label for="password">新密码</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" required>
                    <div class="hint">至少 8 位，需包含大小写字母与数字</div>
                </div>
                <div class="field">
                    <label for="password_confirmation">确认新密码</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                </div>

                <div class="btn-row" style="margin-top:16px">
                    <button class="btn btn-primary" type="submit">保存新密码</button>
                    <a class="btn" href="{{ route('dashboard') }}">返回概览</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="max-width:520px">
        <div class="card-head"><h2 class="card-title">当前账号</h2></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item"><div class="k">姓名</div><div class="v">{{ auth()->user()->name }}</div></div>
                <div class="detail-item"><div class="k">邮箱</div><div class="v">{{ auth()->user()->email }}</div></div>
                <div class="detail-item"><div class="k">角色</div><div class="v">{{ auth()->user()->roleLabel() }}</div></div>
                <div class="detail-item"><div class="k">最近登录</div><div class="v">{{ auth()->user()->last_login_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
            </div>
        </div>
    </div>
@endsection
