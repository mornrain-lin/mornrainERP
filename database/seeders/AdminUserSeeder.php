<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 初始化后台管理员账号
 *
 * 账号与初始密码都从环境变量读取，不在代码里硬编码：
 *   INIT_ADMIN_EMAIL / INIT_ADMIN_PASSWORD
 * 已存在同邮箱账号时不会覆盖其密码，避免重置生产环境口令。
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('INIT_ADMIN_EMAIL', 'admin@mornrain.local');
        $password = env('INIT_ADMIN_PASSWORD');

        if (! $password) {
            $this->command?->warn('未设置 INIT_ADMIN_PASSWORD，跳过管理员初始化。');

            return;
        }

        $exists = User::where('email', $email)->exists();

        if ($exists) {
            $this->command?->line("管理员 {$email} 已存在，保持原密码不变。");

            return;
        }

        User::create([
            'name' => '系统管理员',
            'email' => $email,
            'password' => User::hashPassword($password),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->command?->info("已创建管理员 {$email}，请登录后立即修改密码。");
    }
}
