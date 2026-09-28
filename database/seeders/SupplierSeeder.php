<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * 演示供应商（采购单的供货方）
 * 仅在库空时写入，重复 seed 不会重复创建。
 */
class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        if (Supplier::count() > 0) {
            return;
        }

        $rows = [
            ['name' => '义乌优品供应链', 'contact' => '王经理', 'phone' => '13800001111', 'email' => 'sales@ywyp.cn'],
            ['name' => '深圳跨境仓配', 'contact' => '李主管', 'phone' => '13900002222', 'email' => 'ops@szkg.com'],
        ];

        foreach ($rows as $r) {
            Supplier::create($r + ['is_active' => true]);
        }
    }
}
