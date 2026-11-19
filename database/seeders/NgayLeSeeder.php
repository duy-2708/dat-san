<?php

namespace Database\Seeders;

use App\Models\NgayLe;
use Illuminate\Database\Seeder;

/**
 * Ngày lễ CHUNG (dương lịch cố định). Tết Nguyên Đán và Giỗ Tổ (âm lịch) đổi theo năm:
 * admin bổ sung hằng năm theo thông báo nghỉ lễ chính thức.
 */
class NgayLeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([2026, 2027] as $nam) {
            foreach ([
                "$nam-01-01" => 'Tết Dương lịch',
                "$nam-04-30" => 'Ngày Giải phóng miền Nam',
                "$nam-05-01" => 'Quốc tế Lao động',
                "$nam-09-02" => 'Quốc khánh',
                "$nam-12-24" => 'Giáng sinh',
                "$nam-12-31" => 'Đêm Giao thừa dương lịch',
            ] as $ngay => $ten) {
                if (! NgayLe::whereNull('chu_san_id')->where('ngay', $ngay)->exists()) {
                    NgayLe::create(['ngay' => $ngay, 'ten' => $ten]);
                }
            }
        }
    }
}
