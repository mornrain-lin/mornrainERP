<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 店铺表：一个平台下可以有多个店铺/站点，是多店铺 ERP 的基本单元
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->string('name', 96)->comment('店铺名称');
            $table->string('seller_account', 96)->nullable()->comment('卖家账号');
            $table->string('region', 16)->nullable()->comment('站点，如 MY/TH/SG/US');
            $table->string('currency', 8)->default('CNY')->comment('结算币种');
            $table->decimal('exchange_rate', 12, 6)->default(1)->comment('对人民币汇率');
            $table->boolean('is_active')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['platform_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
