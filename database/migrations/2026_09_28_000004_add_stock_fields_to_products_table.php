<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商品库存字段
 *
 * stock：当前可用库存（发货时自动扣减）
 * safety_stock：安全库存，低于该值触发补货预警
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'stock')) {
                $table->integer('stock')->default(0)->comment('当前库存');
            }
            if (! Schema::hasColumn('products', 'safety_stock')) {
                $table->integer('safety_stock')->default(0)->comment('安全库存');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock', 'safety_stock']);
        });
    }
};
