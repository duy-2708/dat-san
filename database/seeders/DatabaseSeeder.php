<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Chạy: php artisan migrate:fresh --seed */
    public function run(): void
    {
        // Máy chủ thật: chỉ nạp dữ liệu nền, KHÔNG tạo tài khoản mẫu (mật khẩu 12345678 ai cũng biết)
        if (app()->isProduction()) {
            $this->call([TinhThanhSeeder::class, XaPhuongSeeder::class, GoiThueBaoSeeder::class, CaiDatSeeder::class, NgayLeSeeder::class]);

            return;
        }

        $this->call([
            TinhThanhSeeder::class,
            XaPhuongSeeder::class,
            GoiThueBaoSeeder::class,
            CaiDatSeeder::class,
            TaiKhoanMauSeeder::class,
            NgayLeSeeder::class,
            SanMauSeeder::class,
            DatSanMauSeeder::class,
            KeoMauSeeder::class,
            DoiGiaiDauMauSeeder::class,
            ThueBaoMauSeeder::class,
        ]);
    }
}
