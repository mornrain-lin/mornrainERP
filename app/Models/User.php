<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;

/**
 * 后台账号
 *
 * 两级角色：admin（管理员）/ staff（运营）
 */
class User extends Authenticatable
{
    use HasFactory;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'bool',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function roleLabel(): string
    {
        return $this->isAdmin() ? '管理员' : '运营';
    }

    /** 密码统一走 bcrypt，禁止在业务代码里手写 Hash::make */
    public static function hashPassword(string $plain): string
    {
        return Hash::make($plain);
    }

    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN => '管理员',
            self::ROLE_STAFF => '运营',
        ];
    }
}
