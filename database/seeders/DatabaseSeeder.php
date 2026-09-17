<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * mornrainERP 演示数据
     */
    public function run(): void
    {
        $this->call([
            PlatformSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
