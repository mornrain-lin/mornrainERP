<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 后台账号表（幂等）
 *
 * 早期项目初始化时框架自带 users 表已落库，这里既支持全新创建，
 * 也支持在旧表上补齐 mornrainERP 需要的角色 / 启用 / 最近登录字段。
 *
 * 角色只分两级：
 *  - admin：全部权限，含店铺 / 商品 / 平台对接 / 账号管理
 *  - staff：日常经营，订单录入与流转、查看报表
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name', 64)->comment('姓名');
                $table->string('email', 128)->unique()->comment('登录邮箱');
                $table->string('password')->comment('bcrypt 哈希');
                $table->string('role', 16)->default('staff')->comment('角色 admin/staff');
                $table->boolean('is_active')->default(true)->comment('是否启用');
                $table->timestamp('last_login_at')->nullable()->comment('最近登录时间');
                $table->rememberToken();
                $table->timestamps();

                $table->index('role');
            });

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 16)->default('staff')->comment('角色 admin/staff');
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->comment('是否启用');
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->comment('最近登录时间');
            }
            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
