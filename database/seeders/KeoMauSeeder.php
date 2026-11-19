<?php

namespace Database\Seeders;

use App\Models\DatSan;
use App\Services\KeoService;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/** Vài kèo mẫu trên các lịch đã đặt (đã cọc) sắp tới của khách. */
class KeoMauSeeder extends Seeder
{
    public function run(KeoService $keo): void
    {
        $dons = DatSan::with('khach')->where('trang_thai', 'da_dat')->whereNotNull('khach_id')
            ->where('ngay', '>', today()->toDateString())->orderBy('ngay')->get();

        foreach ($dons->take(3) as $i => $don) {
            $d = $i % 2 === 0
                ? ['loai' => 'doi_dau', 'ty_le_thua' => [70, 60, 50][$i % 3], 'trinh_do' => 'trung_binh', 'mo_ta' => 'Tìm đội đá giao lưu, vui là chính.']
                : ['loai' => 'ghep_le', 'so_nguoi_can' => 2, 'tien_moi_nguoi' => 50000, 'tien_coc_ca_nhan' => 20000, 'trinh_do' => 'kha', 'mo_ta' => 'Thiếu 2 người, ưu tiên thủ môn.'];
            try {
                $keo->taoKeo($don->khach, $don, $d);
            } catch (ValidationException) {
                // đã có kèo (chạy seeder lần 2) → bỏ qua
            }
        }
    }
}
