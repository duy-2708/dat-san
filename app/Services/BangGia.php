<?php

namespace App\Services;

use App\Models\KhungGio;
use App\Models\NgayLe;
use App\Models\San;
use App\Support\ThoiGian;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mọi tính toán về khung giờ & giá của sân nằm ở đây (controller chỉ gọi),
 * để giai đoạn 3 (đặt sân) dùng lại đúng một chỗ.
 */
class BangGia
{
    /** Ngày này áp loại giá nào: ngày lễ (chung hoặc riêng chủ sân) > cuối tuần > ngày thường. */
    public function loaiNgay(San $san, CarbonInterface $ngay): string
    {
        $laLe = NgayLe::query()
            ->whereDate('ngay', $ngay->toDateString())
            ->where(fn ($q) => $q->whereNull('chu_san_id')->orWhere('chu_san_id', $san->chu_san_id))
            ->exists();

        if ($laLe) {
            return 'ngay_le';
        }

        return $ngay->isWeekend() ? 'cuoi_tuan' : 'ngay_thuong';
    }

    /**
     * Khung giờ đang áp dụng của sân trong một ngày.
     * Chủ sân chưa đặt giá ngày lễ → dùng giá cuối tuần; chưa có cuối tuần → dùng ngày thường.
     */
    public function khungGioTrongNgay(San $san, CarbonInterface $ngay): Collection
    {
        $thuTu = match ($this->loaiNgay($san, $ngay)) {
            'ngay_le' => ['ngay_le', 'cuoi_tuan', 'ngay_thuong'],
            'cuoi_tuan' => ['cuoi_tuan', 'ngay_thuong'],
            default => ['ngay_thuong'],
        };

        foreach ($thuTu as $loai) {
            $ds = $san->khungGios()->where('loai_ngay', $loai)->where('dang_ap_dung', true)->get();
            if ($ds->isNotEmpty()) {
                return $ds;
            }
        }

        return collect();
    }

    /** Có khung nào cùng sân + cùng loại ngày chồng lên [bd, kt) không. */
    public function biChong(San $san, string $loaiNgay, int $bd, int $kt, ?int $boQuaId = null): bool
    {
        return $san->khungGios()
            ->where('loai_ngay', $loaiNgay)
            ->when($boQuaId, fn ($q) => $q->where('id', '!=', $boQuaId))
            ->get()
            ->contains(fn (KhungGio $k) => ThoiGian::chong($bd, $kt, $k->phutBatDau(), $k->phutKetThuc()));
    }

    /**
     * Sinh hàng loạt khung giờ đều nhau từ giờ mở cửa đến giờ đóng cửa.
     * VD mở 06:00, đóng 23:00, mỗi trận 90' → 06:00–07:30, 07:30–09:00, ... (khung cuối phải kết thúc trước giờ đóng).
     * Khung bắt đầu trong [gioVangTu, gioVangDen) là giờ vàng, dùng giaVang.
     *
     * @param  string[]  $loaiNgays
     * @return int số khung đã tạo
     */
    public function taoNhanh(
        San $san, array $loaiNgays, int $thoiLuong, int $gia,
        ?int $gioVangTu = null, ?int $gioVangDen = null, ?int $giaVang = null, bool $ghiDe = false,
    ): int {
        $mo = ThoiGian::phut($san->gio_mo_cua);
        $dong = ThoiGian::phut($san->gio_dong_cua);
        $soTao = 0;

        DB::transaction(function () use ($san, $loaiNgays, $thoiLuong, $gia, $gioVangTu, $gioVangDen, $giaVang, $ghiDe, $mo, $dong, &$soTao) {
            foreach ($loaiNgays as $loai) {
                if ($ghiDe) {
                    KhungGio::where('san_id', $san->id)->where('loai_ngay', $loai)->delete();
                }
                for ($bd = $mo; $bd + $thoiLuong <= $dong; $bd += $thoiLuong) {
                    $kt = $bd + $thoiLuong;
                    if ($this->biChong($san, $loai, $bd, $kt)) {
                        continue; // giữ khung chủ sân đã tự tạo
                    }
                    $vang = $gioVangTu !== null && $gioVangDen !== null && $bd >= $gioVangTu && $bd < $gioVangDen;
                    $san->khungGios()->create([
                        'loai_ngay' => $loai,
                        'gio_bat_dau' => ThoiGian::csdl($bd),
                        'gio_ket_thuc' => ThoiGian::csdl($kt),
                        'gia' => $vang && $giaVang ? $giaVang : $gia,
                        'la_gio_vang' => $vang,
                        'dang_ap_dung' => true,
                    ]);
                    $soTao++;
                }
            }
        });

        return $soTao;
    }
}
