<?php

namespace Database\Seeders;

use App\Models\GoiThueBao;
use Illuminate\Database\Seeder;

/** Giá TẠM để chạy thử — bạn chốt giá thật rồi sửa ở trang admin (giai đoạn 8). */
class GoiThueBaoSeeder extends Seeder
{
    public function run(): void
    {
        $goi = [
            ['ten' => 'Cơ bản', 'gia_thang' => 299000, 'so_san_toi_da' => 2, 'so_nhan_vien_toi_da' => 1, 'cho_phep_cong_tu_dong' => false,
                'mo_ta' => 'Tối đa 2 sân, 1 nhân viên, xác nhận cọc thủ công qua VietQR.'],
            ['ten' => 'Tiêu chuẩn', 'gia_thang' => 599000, 'so_san_toi_da' => 6, 'so_nhan_vien_toi_da' => 3, 'cho_phep_cong_tu_dong' => true,
                'mo_ta' => 'Tối đa 6 sân, 3 nhân viên, xác nhận cọc tự động qua PayOS.'],
            ['ten' => 'Chuyên nghiệp', 'gia_thang' => 999000, 'so_san_toi_da' => 20, 'so_nhan_vien_toi_da' => 10, 'cho_phep_cong_tu_dong' => true,
                'mo_ta' => 'Tối đa 20 sân, 10 nhân viên, giải đấu, cổng tự động.'],
        ];

        foreach ($goi as $g) {
            GoiThueBao::updateOrCreate(['ten' => $g['ten']], $g);
        }
    }
}
