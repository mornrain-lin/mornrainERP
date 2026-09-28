<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 库存流水
 *
 * 只记录「为什么变」，不记录余额；余额始终以 products.stock 为准，
 * 两者不一致时用盘点（adjust）把库存拉回实际值。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16)->comment('in 入库 / out 出库 / adjust 盘点');
            $table->integer('quantity')->comment('正数入库，负数出库');
            $table->integer('balance_after')->default(0)->comment('变动后库存');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('remark', 255)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
