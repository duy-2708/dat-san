<?php

namespace Database\Seeders;

use App\Models\San;
use App\Models\User;
use App\Models\XaPhuong;
use App\Services\BangGia;
use App\Support\ThoiGian;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sân mẫu:
 *  chusan1 (TP.HCM): Sân 5A, Sân 5B, Sân 7 Lớn (ghép 5A + 5B), Sân 7C
 *  chusan2 (Đà Nẵng): Hải Châu 1 (5), Hải Châu 2 (5), Sân 11 Hải Châu (ghép HC1 + HC2 + HC3), Hải Châu 3 (7)
 *  chusan3 (Cần Thơ, hết hạn): Ninh Kiều 1 (5)
 * Mỗi sân có khung 90' ngày thường + cuối tuần, giờ vàng 17:00–21:00.
 */
class SanMauSeeder extends Seeder
{
    public function run(BangGia $bangGia): void
    {
        $xp = fn (string $ten) => XaPhuong::where('ten', $ten)->value('id') ?? XaPhuong::value('id');

        $ds = [
            'chusan1@datsan.test' => [
                ['Sân 5A', 5, 'Phường Sài Gòn', '12 Lê Lợi', 300000, 450000, []],
                ['Sân 5B', 5, 'Phường Sài Gòn', '12 Lê Lợi', 300000, 450000, []],
                ['Sân 7 Lớn', 7, 'Phường Sài Gòn', '12 Lê Lợi', 550000, 800000, ['Sân 5A', 'Sân 5B']],
                ['Sân 7C', 7, 'Phường Tân Định', '45 Hai Bà Trưng', 500000, 750000, []],
            ],
            'chusan2@datsan.test' => [
                ['Hải Châu 1', 5, 'Phường Hải Châu', '88 Bạch Đằng', 250000, 380000, []],
                ['Hải Châu 2', 5, 'Phường Hải Châu', '88 Bạch Đằng', 250000, 380000, []],
                ['Hải Châu 3', 7, 'Phường Hải Châu', '88 Bạch Đằng', 450000, 650000, []],
                ['Sân 11 Hải Châu', 11, 'Phường Hải Châu', '88 Bạch Đằng', 1200000, 1600000, ['Hải Châu 1', 'Hải Châu 2', 'Hải Châu 3']],
            ],
            'chusan3@datsan.test' => [
                ['Ninh Kiều 1', 5, 'Phường Ninh Kiều', '5 Hai Bà Trưng', 220000, 320000, []],
            ],
        ];

        foreach ($ds as $email => $sans) {
            $chu = User::where('email', $email)->first();
            if (! $chu) {
                continue;
            }
            foreach ($sans as [$ten, $loai, $phuong, $diaChi, $gia, $giaVang, $cons]) {
                if (San::where('chu_san_id', $chu->id)->where('ten', $ten)->exists()) {
                    continue;
                }
                $san = new San([
                    'ten' => $ten, 'loai_san' => $loai, 'xa_phuong_id' => $xp($phuong), 'dia_chi_chi_tiet' => $diaChi,
                    'mo_ta' => 'Sân cỏ nhân tạo, đèn LED, có chỗ giữ xe.',
                    'tien_ich' => ['den_chieu_sang', 'gui_xe', 'nuoc_uong', 'wc'],
                    'gio_mo_cua' => '06:00:00', 'gio_dong_cua' => '24:00:00',
                ]);
                $san->chu_san_id = $chu->id;
                $san->slug = Str::slug($ten).'-'.Str::lower(Str::random(6));
                $san->save();

                if ($cons) {
                    $san->sanCons()->sync(San::where('chu_san_id', $chu->id)->whereIn('ten', $cons)->pluck('id'));
                }

                $bangGia->taoNhanh($san, ['ngay_thuong', 'cuoi_tuan'], 90, $gia,
                    ThoiGian::phut('17:00'), ThoiGian::phut('21:00'), $giaVang);
            }
        }
    }
}
