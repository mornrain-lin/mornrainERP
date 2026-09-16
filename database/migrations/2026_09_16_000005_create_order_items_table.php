<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 订单明细表
 * line_total / line_cost 冗余存储，便于列表页用 withSum 聚合，避免 N+1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('sku', 64);
            $table->string('product_name', 160)->comment('下单时商品名快照');
            $table->unsignedInteger('quantity')->default(1);

            $table->decimal('unit_price', 12, 2)->default(0)->comment('售价(平台币)');
            $table->decimal('unit_cost', 12, 2)->default(0)->comment('成本快照(CNY)');
            $table->decimal('line_total', 14, 2)->default(0)->comment('售价小计(平台币)');
            $table->decimal('line_cost', 14, 2)->default(0)->comment('成本小计(CNY)');

            $table->timestamps();

            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
