<?php

namespace App\Services;

use App\Models\GoiThueBao;
use App\Models\HoSoChuSan;
use App\Models\ThueBao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mua / gia hạn gói:
 *  1. Chủ sân chọn gói + số tháng → đơn thuê bao "chờ thanh toán", hiện QR chuyển khoản cho admin.
 *  2. Admin thấy tiền về → bấm xác nhận → cộng hạn (nối tiếp hạn cũ nếu còn) + đổi gói.
 * Mỗi chủ sân chỉ có 1 đơn chờ; tạo đơn mới thì đơn chờ cũ tự huỷ.
 */
class ThueBaoService
{
    public const SO_THANG = [1, 3, 6, 12];

    public function taoDon(User $chu, GoiThueBao $goi, int $soThang): ThueBao
    {
        if (! $goi->dang_ban) {
            $this->loi('Gói này đã ngừng bán.');
        }
        if (! in_array($soThang, self::SO_THANG, true)) {
            $this->loi('Số tháng không hợp lệ.');
        }
        $hoSo = $chu->hoSoChuSan;
        $soSan = $chu->sans()->count();
        if ($soSan > $goi->so_san_toi_da) {
            $this->loi("Bạn đang có {$soSan} sân, gói {$goi->ten} chỉ cho {$goi->so_san_toi_da}. Chọn gói lớn hơn hoặc ngừng bớt sân.");
        }

        return DB::transaction(function () use ($chu, $goi, $soThang, $hoSo) {
            ThueBao::where('chu_san_id', $chu->id)->where('trang_thai', 'cho_thanh_toan')->update(['trang_thai' => 'da_huy']);

            [$tu, $den] = $this->khoangThoiGian($hoSo, $soThang);
            $tb = new ThueBao;
            $tb->forceFill([
                'chu_san_id' => $chu->id, 'goi_thue_bao_id' => $goi->id, 'so_thang' => $soThang,
                'so_tien' => (int) $goi->gia_thang * $soThang, 'tu_ngay' => $tu, 'den_ngay' => $den,
                'trang_thai' => 'cho_thanh_toan',
            ])->save();

            return $tb;
        });
    }

    public function xacNhan(ThueBao $tb, User $admin): void
    {
        DB::transaction(function () use ($tb, $admin) {
            $tb = ThueBao::lockForUpdate()->findOrFail($tb->id);
            if ($tb->trang_thai !== 'cho_thanh_toan') {
                $this->loi('Đơn này không còn chờ thanh toán.');
            }
            $hoSo = HoSoChuSan::where('user_id', $tb->chu_san_id)->lockForUpdate()->firstOrFail();
            [$tu, $den] = $this->khoangThoiGian($hoSo, $tb->so_thang); // tính lại lúc xác nhận

            $tb->forceFill(['trang_thai' => 'da_thanh_toan', 'tu_ngay' => $tu, 'den_ngay' => $den,
                'xac_nhan_boi_id' => $admin->id, 'xac_nhan_luc' => now()])->save();
            $hoSo->forceFill(['goi_thue_bao_id' => $tb->goi_thue_bao_id, 'het_han_luc' => $den,
                'bi_khoa_do_het_han' => false, 'da_nhac_het_han_luc' => null])->save();
        });
    }

    public function huy(ThueBao $tb): void
    {
        if ($tb->trang_thai !== 'cho_thanh_toan') {
            $this->loi('Chỉ huỷ được đơn đang chờ thanh toán.');
        }
        $tb->forceFill(['trang_thai' => 'da_huy'])->save();
    }

    /** Admin gia hạn tay (tặng ngày, khách trả tiền mặt...). Ghi lại thành 1 dòng thuê bao đã thanh toán. */
    public function giaHanTay(User $chu, User $admin, int $soNgay, ?GoiThueBao $goi, int $soTien = 0, ?string $ghiChu = null): void
    {
        DB::transaction(function () use ($chu, $admin, $soNgay, $goi, $soTien, $ghiChu) {
            $hoSo = HoSoChuSan::where('user_id', $chu->id)->lockForUpdate()->firstOrFail();
            $goi ??= $hoSo->goiThueBao;
            if (! $goi) {
                $this->loi('Chọn gói cho chủ sân này.');
            }
            $tu = $hoSo->het_han_luc?->isFuture() ? $hoSo->het_han_luc->copy() : now();
            $den = $tu->copy()->addDays($soNgay);

            $tb = new ThueBao;
            $tb->forceFill([
                'chu_san_id' => $chu->id, 'goi_thue_bao_id' => $goi->id, 'so_thang' => max(1, (int) round($soNgay / 30)),
                'so_tien' => $soTien, 'tu_ngay' => $tu, 'den_ngay' => $den, 'trang_thai' => 'da_thanh_toan',
                'xac_nhan_boi_id' => $admin->id, 'xac_nhan_luc' => now(), 'ghi_chu' => $ghiChu ?: "Admin gia hạn tay {$soNgay} ngày",
            ])->save();
            $hoSo->forceFill(['goi_thue_bao_id' => $goi->id, 'het_han_luc' => $den,
                'bi_khoa_do_het_han' => false, 'da_nhac_het_han_luc' => null])->save();
        });
    }

    /** Nối tiếp hạn cũ nếu còn, nếu đã hết thì tính từ bây giờ. */
    private function khoangThoiGian(?HoSoChuSan $hoSo, int $soThang): array
    {
        $tu = $hoSo?->het_han_luc?->isFuture() ? $hoSo->het_han_luc->copy() : now();

        return [$tu, $tu->copy()->addMonthsNoOverflow($soThang)];
    }

    private function loi(string $s): never
    {
        throw ValidationException::withMessages(['thue_bao' => $s]);
    }
}
