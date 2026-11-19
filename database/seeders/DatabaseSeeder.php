<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Chạy: php artisan migrate:fresh --seed */
    public function run(): void
    {
        $this->call([
            TinhThanhSeeder::class,
            XaPhuongSeeder::class,
            GoiThueBaoSeeder::class,
            TaiKhoanMauSeeder::class,
            NgayLeSeeder::class,
            SanMauSeeder::class,
            DatSanMauSeeder::class,
        ]);
    }
}
