<?php

namespace Database\Seeders;

use App\Models\GiaoDich;
use App\Models\San;
use App\Models\User;
use App\Services\BangGia;
use App\Services\DatSanService;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/**
 * Đơn đặt mẫu trải nhiều ngày (quá khứ → 6 ngày tới):
 *  - đã đặt online (đã cọc), đặt tại chỗ, đang giữ chỗ chờ cọc, đã huỷ chờ hoàn cọc
 *  - "Sân 7 Lớn" được đặt → các khung trùng giờ của Sân 5A, 5B tự hiện "Đã đặt" (sân ghép)
 */
class DatSanMauSeeder extends Seeder
{
    public function run(BangGia $bangGia, DatSanService $dv): void
    {
        $khach = User::where('vai_tro', 'khach')->where('trang_thai', 'hoat_dong')->orderBy('id')->get()->values();
        $chu1 = User::where('email', 'chusan1@datsan.test')->first();
        if (! $chu1 || $khach->isEmpty()) {
            return;
        }

        // [tên sân, ngày lệch so với hôm nay, chỉ số khung trong ngày, kiểu, chỉ số khách]
        $mau = [
            ['Sân 5A', -3, 8, 'online', 0], ['Sân 5A', -3, 9, 'tai_cho', null], ['Sân 7C', -2, 9, 'online', 1],
            ['Sân 5B', -1, 7, 'online', 2], ['Sân 7C', -1, 10, 'tai_cho', null],
            ['Sân 5A', 0, 10, 'online', 0], ['Sân 5B', 0, 11, 'tai_cho', null], ['Sân 7C', 0, 9, 'online', 3],
            ['Sân 7 Lớn', 1, 9, 'online', 1],       // khoá luôn 5A + 5B giờ đó
            ['Sân 5A', 1, 10, 'giu_cho', 4],         // đang chờ cọc (hết hạn sau 15')
            ['Sân 7C', 2, 9, 'online', 2], ['Sân 5B', 2, 10, 'tai_cho', null],
            ['Sân 5A', 3, 8, 'huy_cho_hoan', 3],
            ['Sân 7C', 4, 9, 'online', 0], ['Sân 7 Lớn', 5, 10, 'tai_cho', null], ['Sân 5A', 6, 9, 'online', 1],
        ];

        $tenTaiCho = ['Anh Tuấn (đội Phú Nhuận)', 'Chị Lan', 'Đội Sinh viên BK', 'Anh Hùng'];

        foreach ($mau as $i => [$tenSan, $lech, $chiSo, $kieu, $kh]) {
            $san = San::where('chu_san_id', $chu1->id)->where('ten', $tenSan)->first();
            $ngay = today()->addDays($lech);
            $khung = $san ? $bangGia->khungGioTrongNgay($san, $ngay)->values()->get($chiSo) : null;
            if (! $khung) {
                continue;
            }
            $u = $kh !== null ? $khach->get($kh % $khach->count()) : null;
            $online = $u !== null;

            try {
                $don = $dv->ghiDon($san, $ngay->toDateString(), $khung->phutBatDau(), $khung->phutKetThuc(), [
                    'khach_id' => $u?->id,
                    'ten_khach_tai_cho' => $online ? null : $tenTaiCho[$i % count($tenTaiCho)],
                    'sdt_khach_tai_cho' => $online ? null : '09880000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'khung_gio_id' => $khung->id,
                    'gia_chot' => $khung->gia,
                    'tien_coc' => $kieu === 'tai_cho' ? 0 : $khung->tienCoc(),
                    'nguon' => $online ? 'online' : 'tai_cho',
                    'trang_thai' => $kieu === 'giu_cho' ? 'cho_thanh_toan' : 'da_dat',
                    'giu_cho_den' => $kieu === 'giu_cho' ? now()->addMinutes(15) : null,
                    'xac_nhan_coc_luc' => $online && $kieu !== 'giu_cho' ? now() : null,
                    'xac_nhan_coc_boi_id' => $online && $kieu !== 'giu_cho' ? $chu1->id : null,
                    'nguoi_tao_id' => $online ? null : $chu1->id,
                ]);
            } catch (ValidationException) {
                continue; // trùng ô (chạy seeder lần 2) → bỏ qua
            }

            if ($online) {
                $gd = new GiaoDich;
                $gd->forceFill([
                    'loai' => 'coc_dat_san', 'dat_san_id' => $don->id, 'chu_san_id' => $chu1->id, 'nguoi_tra_id' => $u->id,
                    'phuong_thuc' => 'vietqr', 'noi_dung_ck' => $don->ma_don, 'so_tien' => $don->tien_coc,
                    'trang_thai' => $kieu === 'giu_cho' ? 'cho_thanh_toan' : 'thanh_cong',
                    'thanh_toan_luc' => $kieu === 'giu_cho' ? null : now(), 'xac_nhan_boi_id' => $kieu === 'giu_cho' ? null : $chu1->id,
                ])->save();
            }

            if ($kieu === 'huy_cho_hoan') {
                $dv->huy($don, $chu1, 'Sân bảo trì đèn');
            }
        }
    }
}
