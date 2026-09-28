<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // 物流轨迹时间线（JSON 数组：[{time, status, desc}]）
            $table->text('events')->nullable()->after('tracking_no');
            // 最近一次查询轨迹的时间
            $table->timestamp('last_tracked_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['events', 'last_tracked_at']);
        });
    }
};
