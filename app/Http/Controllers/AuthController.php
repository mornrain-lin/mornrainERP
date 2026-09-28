<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * 登录 / 登出 / 修改密码
 */
class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => '请输入登录邮箱',
            'email.email' => '邮箱格式不正确',
            'password.required' => '请输入密码',
        ]);

        $user = User::where('email', $data['email'])->first();

        // 统一错误文案，避免暴露账号是否存在
        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => '邮箱或密码不正确，或账号已停用']);
        }

        Auth::login($user, $request->boolean('remember'));
        $user->forceFill(['last_login_at' => now()])->save();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))
            ->with('ok', '欢迎回来，' . $user->name);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('ok', '已安全退出');
    }

    public function passwordForm()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ], [
            'current_password.required' => '请输入当前密码',
            'current_password.current_password' => '当前密码不正确',
            'password.required' => '请输入新密码',
            'password.confirmed' => '两次输入的新密码不一致',
        ]);

        $request->user()->forceFill(['password' => User::hashPassword($data['password'])])->save();

        return redirect()->route('dashboard')->with('ok', '密码已更新，下次登录请使用新密码');
    }
}
