<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 发货/物流表：一个订单可拆多包裹，也是最容易被小团队漏掉的对账环节
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('carrier', 64)->nullable()->comment('物流商');
            $table->string('tracking_no', 96)->nullable()->comment('运单号');
            $table->string('status', 20)->default('pending')->comment('pending/in_transit/delivered/failed');
            $table->decimal('cost', 12, 2)->default(0)->comment('物流费用(CNY)');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('tracking_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
