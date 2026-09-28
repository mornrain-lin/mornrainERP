<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 平台同步日志
 *
 * 每一次拉单（手动点击或定时任务）都留痕，方便排查「为什么订单没进来」。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trigger', 16)->default('manual')->comment('manual/command/schedule');
            $table->string('status', 16)->default('success')->comment('success/failed');
            $table->timestamp('range_from')->nullable()->comment('拉取起始时间');
            $table->timestamp('range_to')->nullable()->comment('拉取结束时间');
            $table->unsignedInteger('fetched')->default(0)->comment('平台返回订单数');
            $table->unsignedInteger('created')->default(0)->comment('新建订单数');
            $table->unsignedInteger('updated')->default(0)->comment('更新订单数');
            $table->unsignedInteger('skipped')->default(0)->comment('跳过订单数');
            $table->unsignedInteger('duration_ms')->default(0)->comment('耗时(毫秒)');
            $table->text('message')->nullable()->comment('结果说明 / 错误原因');
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
