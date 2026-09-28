<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 店铺 API 对接配置
 *
 * app_secret / access_token 走 Eloquent encrypted cast，落库即密文，
 * 即使数据库被导出也无法直接拿到平台凭证。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('api_base', 255)->nullable()->comment('平台 OpenAPI 根地址');
            $table->string('app_key', 128)->nullable()->comment('平台 AppKey / ClientId');
            $table->text('app_secret')->nullable()->comment('平台 AppSecret（密文存储）');
            $table->text('access_token')->nullable()->comment('平台访问令牌（密文存储）');
            $table->timestamp('token_expires_at')->nullable()->comment('令牌过期时间');
            $table->boolean('sync_enabled')->default(false)->comment('是否开启自动拉单');
            $table->timestamp('last_synced_at')->nullable()->comment('最近同步时间');
            $table->string('last_sync_status', 16)->nullable()->comment('最近同步结果 success/failed');
            $table->text('last_sync_message')->nullable()->comment('最近同步结果说明');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'api_base', 'app_key', 'app_secret', 'access_token', 'token_expires_at',
                'sync_enabled', 'last_synced_at', 'last_sync_status', 'last_sync_message',
            ]);
        });
    }
};
