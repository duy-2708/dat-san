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
            // Giai đoạn 2 sẽ thêm: SanMauSeeder (sân, ảnh, sân ghép, khung giờ), NgayLeSeeder
            // Giai đoạn 3 sẽ thêm: DatSanMauSeeder (đơn đặt nhiều ngày)
        ]);
    }
}
