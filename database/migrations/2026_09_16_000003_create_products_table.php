<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商品/SKU 表：利润核算的核心——记录采购成本（人民币）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique()->comment('SKU 编码');
            $table->string('name', 160)->comment('商品名称');
            $table->string('category', 64)->nullable()->comment('品类');
            $table->decimal('cost_price', 12, 2)->default(0)->comment('采购成本（CNY）');
            $table->decimal('weight_g', 10, 2)->default(0)->comment('重量（克）');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
