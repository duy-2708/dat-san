<?php

namespace App\Services;

use App\Models\DatSan;
use App\Models\GiaoDich;
use Carbon\Carbon;

class ThongKeThu
{
    public function thang(int $chuSanId, Carbon $thang): array
    {
        $tu = $thang->copy()->startOfMonth();
        $den = $thang->copy()->endOfMonth();
        $gd = GiaoDich::where('chu_san_id', $chuSanId)->where('trang_thai', 'thanh_cong')->whereBetween('thanh_toan_luc', [$tu, $den]);
        $cuaSan = fn ($q) => $q->whereHas('san', fn ($s) => $s->where('chu_san_id', $chuSanId));

        $cocOnline = (int) (clone $gd)->whereIn('phuong_thuc', ['vietqr', 'payos'])->sum('so_tien');
        $cocNgoai = (int) (clone $gd)->where('phuong_thuc', 'tien_mat')->sum('so_tien');
        $tienSan = (int) DatSan::where($cuaSan)->whereBetween('da_thu_tien_luc', [$tu, $den])->sum('tien_thu_tai_san');
        $hoan = (int) DatSan::where($cuaSan)->where('xu_ly_coc', 'da_hoan')->whereBetween('da_hoan_luc', [$tu, $den])->sum('tien_coc')
            + (int) GiaoDich::where('chu_san_id', $chuSanId)->where('xu_ly_hoan', 'da_hoan')->whereNull('dat_san_id')->whereBetween('da_hoan_luc', [$tu, $den])->sum('so_tien');
        $chuaThu = DatSan::where($cuaSan)->where('trang_thai', 'da_dat')->whereNull('da_thu_tien_luc')
            ->whereBetween('ngay', [$tu->toDateString(), min($den, now())->toDateString()])->get()
            ->filter(fn ($d) => $d->batDauLuc()->isPast());

        return [
            'coc_online' => $cocOnline,
            'coc_ngoai' => $cocNgoai,
            'tien_san' => $tienSan,
            'hoan' => $hoan,
            'tong' => $cocOnline + $cocNgoai + $tienSan - $hoan,
            'so_don' => DatSan::where($cuaSan)->where('trang_thai', 'da_dat')->whereBetween('ngay', [$tu->toDateString(), $den->toDateString()])->count(),
            'chua_thu' => $chuaThu,
        ];
    }
}
