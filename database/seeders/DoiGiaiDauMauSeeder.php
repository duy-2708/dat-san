<?php

namespace Database\Seeders;

use App\Models\GiaiDau;
use App\Models\User;
use App\Services\DoiService;
use App\Services\GiaiDauService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 4 đội mẫu (khach1..khach4 làm đội trưởng, khach5 ở 2 đội),
 * 1 giải vòng tròn ĐÃ KẾT THÚC (có kết quả, thứ hạng, danh hiệu) và 1 giải đang mở đăng ký.
 */
class DoiGiaiDauMauSeeder extends Seeder
{
    public function run(DoiService $doiSv, GiaiDauService $giaiSv): void
    {
        $k = User::where('vai_tro', 'khach')->where('trang_thai', 'hoat_dong')->orderBy('id')->get()->values();
        $chu = User::where('email', 'chusan1@datsan.test')->first();
        if ($k->count() < 5 || ! $chu || GiaiDau::exists()) {
            return;
        }

        $tenDoi = ['FC Phú Nhuận', 'Sài Gòn United', 'Tân Định Boys', 'Bến Thành FC'];
        $doi = [];
        foreach ($tenDoi as $i => $ten) {
            $doi[$i] = $doiSv->taoDoi($k[$i], ['ten' => $ten, 'mo_ta' => 'Đội phong trào, đá tối thứ 3 và thứ 6.'], null);
            $doiSv->themThanhVien($k[$i], $doi[$i], $k[4], $i + 7);
        }
        foreach ([[0, 'tien_dao'], [1, 'tien_ve'], [2, 'hau_ve'], [3, 'thu_mon'], [4, 'tien_dao']] as [$i, $vt]) {
            $k[$i]->forceFill(['vi_tri_so_truong' => $vt])->save();
        }

        $tao = function (string $ten, string $tu, string $den) use ($chu) {
            $g = new GiaiDau;
            $g->forceFill(['chu_san_id' => $chu->id, 'ten' => $ten, 'slug' => Str::slug($ten).'-'.Str::lower(Str::random(5)),
                'loai_san' => 7, 'the_thuc' => 'vong_tron', 'tu_ngay' => $tu, 'den_ngay' => $den, 'le_phi' => 500000,
                'so_doi_toi_da' => 8, 'mo_ta' => 'Đá vòng tròn 1 lượt, thắng 3 điểm, hoà 1 điểm.', 'trang_thai' => 'mo_dang_ky'])->save();

            return $g;
        };

        // Giải đã kết thúc
        $g1 = $tao('Cúp Phú Nhuận mùa thu 2026', today()->subDays(40)->toDateString(), today()->subDays(20)->toDateString());
        foreach ($doi as $i => $d) {
            $dk = $giaiSv->dangKy($k[$i], $g1, $d, $d->dangChoi()->pluck('user_id')->all());
            $giaiSv->duyet($dk, true);
        }
        $giaiSv->dongDangKy($g1);
        $tiSo = [[3, 1], [2, 2], [0, 1], [4, 0], [1, 1], [2, 3]];
        foreach ($g1->tranDaus()->get() as $j => $t) {
            $giaiSv->nhapKetQua($t, ...$tiSo[$j % count($tiSo)]);
        }
        $hang = [];
        foreach ($giaiSv->bangXepHang($g1->fresh()) as $i => $r) {
            $hang[$r['dk']->id] = ['thu_hang' => $i + 1, 'danh_hieu' => ['Vô địch', 'Á quân', 'Hạng ba'][$i] ?? null];
        }
        $giaiSv->ketThuc($g1->fresh(), $hang);

        // Giải đang mở đăng ký
        $g2 = $tao('Giải Sân 7 Tết 2027', today()->addDays(30)->toDateString(), today()->addDays(45)->toDateString());
        $giaiSv->dangKy($k[0], $g2, $doi[0], $doi[0]->dangChoi()->pluck('user_id')->all());
    }
}
