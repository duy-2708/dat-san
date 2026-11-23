<?php

namespace App\Services;

use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\ThuTienKeo;
use Carbon\Carbon;

class ThongKeThu
{
    public function thang(int $chuSanId, Carbon $thang): array
    {
        $tu = $thang->copy()->startOfMonth();
        $den = $thang->copy()->endOfMonth();
        $gd = GiaoDich::where('chu_san_id', $chuSanId)->where('trang_thai', 'thanh_cong')->whereBetween('thanh_toan_luc', [$tu, $den]);
        $cuaSan = fn ($q) => $q->whereHas('san', fn ($s) => $s->where('chu_san_id', $chuSanId));
        $thuKeo = ThuTienKeo::whereHas('keo.datSan.san', fn ($s) => $s->where('chu_san_id', $chuSanId))->whereBetween('thu_luc', [$tu, $den]);

        $cocOnline = (int) (clone $gd)->whereIn('phuong_thuc', ['vietqr', 'payos'])->sum('so_tien');
        $cocNgoai = (int) (clone $gd)->where('phuong_thuc', 'tien_mat')->sum('so_tien');
        $tienSan = (int) DatSan::where($cuaSan)->whereBetween('da_thu_tien_luc', [$tu, $den])->sum('tien_thu_tai_san');
        $tienSanMat = (int) DatSan::where($cuaSan)->where('phuong_thuc_thu_tai_san', 'tien_mat')->whereBetween('da_thu_tien_luc', [$tu, $den])->sum('tien_thu_tai_san');
        $tienSanOnline = (int) DatSan::where($cuaSan)->where('phuong_thuc_thu_tai_san', 'online')->whereBetween('da_thu_tien_luc', [$tu, $den])->sum('tien_thu_tai_san');
        $keoThuMat = (int) (clone $thuKeo)->where('loai', 'thu')->where('phuong_thuc', 'tien_mat')->sum('so_tien');
        $keoThuOnline = (int) (clone $thuKeo)->where('loai', 'thu')->where('phuong_thuc', 'online')->sum('so_tien');
        $keoTraLaiMat = (int) (clone $thuKeo)->where('loai', 'tra_lai')->where('phuong_thuc', 'tien_mat')->sum('so_tien');
        $keoTraLaiOnline = (int) (clone $thuKeo)->where('loai', 'tra_lai')->where('phuong_thuc', 'online')->sum('so_tien');
        $hoan = (int) DatSan::where($cuaSan)->where('xu_ly_coc', 'da_hoan')->whereBetween('da_hoan_luc', [$tu, $den])->sum('tien_coc')
            + (int) GiaoDich::where('chu_san_id', $chuSanId)->where('xu_ly_hoan', 'da_hoan')->whereNull('dat_san_id')->whereBetween('da_hoan_luc', [$tu, $den])->sum('so_tien');
        $chuaThu = DatSan::where($cuaSan)->where('trang_thai', 'da_dat')->whereNull('da_thu_tien_luc')
            ->whereBetween('ngay', [$tu->toDateString(), min($den, now())->toDateString()])->get()
            ->filter(fn ($d) => $d->batDauLuc()->isPast());

        $tienMat = $cocNgoai + $tienSanMat + $keoThuMat - $keoTraLaiMat;
        $online = $cocOnline + $tienSanOnline + $keoThuOnline - $keoTraLaiOnline;
        $tienSanChuaRo = $tienSan - $tienSanMat - $tienSanOnline;

        return [
            'coc_online' => $cocOnline,
            'coc_ngoai' => $cocNgoai,
            'tien_san' => $tienSan,
            'tien_san_chua_ro' => $tienSanChuaRo,
            'keo' => $keoThuMat + $keoThuOnline - $keoTraLaiMat - $keoTraLaiOnline,
            'hoan' => $hoan,
            'tien_mat' => $tienMat,
            'online' => $online,
            'tong' => $tienMat + $online + $tienSanChuaRo - $hoan,
            'so_don' => DatSan::where($cuaSan)->where('trang_thai', 'da_dat')->whereBetween('ngay', [$tu->toDateString(), $den->toDateString()])->count(),
            'chua_thu' => $chuaThu,
        ];
    }
}
