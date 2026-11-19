<?php

namespace Database\Seeders;

use App\Models\CaiDat;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu mẫu: CHƯA bật miễn phí để thấy được chủ sân hết hạn (chusan3).
 * Muốn mở miễn phí: Admin → Cài đặt → "Miễn phí đến ngày".
 */
class CaiDatSeeder extends Seeder
{
    public function run(): void
    {
        CaiDat::dat([
            'mien_phi_den' => null,
            'tk_bin' => '970436',
            'tk_so' => '0011223344',
            'tk_ten' => 'QUAN TRI DAT SAN',
            'sdt_ho_tro' => '0900000001',
        ]);
    }
}
