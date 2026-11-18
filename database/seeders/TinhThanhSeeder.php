<?php

namespace Database\Seeders;

use App\Models\TinhThanh;
use Illuminate\Database\Seeder;

/** 34 tỉnh/thành phố theo Nghị quyết 202/2025/QH15, hiệu lực 1/7/2025. */
class TinhThanhSeeder extends Seeder
{
    public function run(): void
    {
        $thanhPho = ['Hà Nội', 'Hồ Chí Minh', 'Hải Phòng', 'Đà Nẵng', 'Cần Thơ', 'Huế'];

        $tinh = [
            'An Giang', 'Bắc Ninh', 'Cà Mau', 'Cao Bằng', 'Đắk Lắk', 'Điện Biên', 'Đồng Nai',
            'Đồng Tháp', 'Gia Lai', 'Hà Tĩnh', 'Hưng Yên', 'Khánh Hòa', 'Lai Châu', 'Lâm Đồng',
            'Lạng Sơn', 'Lào Cai', 'Nghệ An', 'Ninh Bình', 'Phú Thọ', 'Quảng Ngãi', 'Quảng Ninh',
            'Quảng Trị', 'Sơn La', 'Tây Ninh', 'Thái Nguyên', 'Thanh Hóa', 'Tuyên Quang', 'Vĩnh Long',
        ];

        $thuTu = 0;
        foreach ($thanhPho as $ten) {
            TinhThanh::updateOrCreate(['ten' => $ten], ['loai' => 'thanh_pho', 'thu_tu' => ++$thuTu]);
        }
        foreach ($tinh as $ten) {
            TinhThanh::updateOrCreate(['ten' => $ten], ['loai' => 'tinh', 'thu_tu' => ++$thuTu]);
        }
    }
}
