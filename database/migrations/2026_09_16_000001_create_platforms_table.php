<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 平台表：Shopee / Lazada / Amazon / TikTok Shop / Temu / AliExpress / Shopify(独立站)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique()->comment('平台代号');
            $table->string('name', 64)->comment('平台名称');
            $table->string('region', 64)->nullable()->comment('主要站点');
            $table->decimal('commission_rate', 6, 3)->default(0)->comment('默认佣金率 %');
            $table->decimal('payment_fee_rate', 6, 3)->default(0)->comment('默认支付手续费率 %');
            $table->string('color', 16)->default('#2f6fed')->comment('UI 标识色');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
