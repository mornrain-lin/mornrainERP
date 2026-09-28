<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * 账号管理（仅管理员）
 *
 * 小团队场景不做复杂 RBAC，只维护两级角色：admin / staff
 */
class UserController extends Controller
{
    public function index()
    {
        $users = User::orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->paginate(20);

        return view('users.index', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'email' => ['required', 'email', 'max:128', 'unique:users,email'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()],
            'role' => ['required', 'in:admin,staff'],
        ], [
            'name.required' => '请输入姓名',
            'email.required' => '请输入登录邮箱',
            'email.unique' => '该邮箱已存在',
            'password.required' => '请输入初始密码',
            'role.in' => '角色不正确',
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => User::hashPassword($data['password']),
            'role' => $data['role'],
            'is_active' => true,
        ]);

        return back()->with('ok', "账号 {$data['name']} 创建成功");
    }

    public function toggle(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('err', '不能停用自己当前登录的账号');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return back()->with('ok', $user->is_active ? "账号 {$user->name} 已启用" : "账号 {$user->name} 已停用");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', Password::min(8)->mixedCase()->numbers()],
        ], ['password.required' => '请输入新密码']);

        $user->forceFill(['password' => User::hashPassword($data['password'])])->save();

        return back()->with('ok', "账号 {$user->name} 的密码已重置");
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('err', '不能删除自己当前登录的账号');
        }

        if ($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return back()->with('err', '至少保留一个管理员账号');
        }

        $user->delete();

        return back()->with('ok', "账号 {$user->name} 已删除");
    }
}
