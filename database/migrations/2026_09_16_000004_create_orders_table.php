<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 订单表
 *
 * 金额口径说明（关键）：
 *  - 平台币字段：goods_amount / shipping_income / discount_amount /
 *    platform_commission / payment_fee / refund_amount
 *  - 人民币字段：shipping_cost / ad_cost / other_cost（卖家实际支出的物流、广告、其他）
 *  - exchange_rate 负责把平台币换算成人民币
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 64)->unique()->comment('平台订单号');
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();

            $table->string('buyer_name', 96)->nullable();
            $table->string('buyer_country', 8)->nullable();
            $table->string('currency', 8)->default('CNY');
            $table->decimal('exchange_rate', 12, 6)->default(1)->comment('对人民币汇率');

            // ---- 平台币金额 / 成本 ----
            $table->decimal('goods_amount', 14, 2)->default(0)->comment('商品金额');
            $table->decimal('shipping_income', 12, 2)->default(0)->comment('向买家收取的运费');
            $table->decimal('discount_amount', 12, 2)->default(0)->comment('平台补贴/优惠');
            $table->decimal('platform_commission', 12, 2)->default(0)->comment('平台佣金');
            $table->decimal('payment_fee', 12, 2)->default(0)->comment('支付手续费');
            $table->decimal('refund_amount', 12, 2)->default(0)->comment('退款金额');

            // ---- 人民币成本 ----
            $table->decimal('shipping_cost', 12, 2)->default(0)->comment('物流成本(CNY)');
            $table->decimal('ad_cost', 12, 2)->default(0)->comment('广告分摊(CNY)');
            $table->decimal('other_cost', 12, 2)->default(0)->comment('其他成本(CNY)');

            $table->string('status', 20)->default('pending')->comment('订单状态');
            $table->string('source', 20)->default('manual')->comment('来源 manual/import/api');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('remark')->nullable();

            $table->timestamps();

            $table->index(['platform_id', 'status']);
            $table->index(['shop_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
