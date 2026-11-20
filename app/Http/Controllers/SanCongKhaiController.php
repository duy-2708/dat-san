<?php

namespace App\Http\Controllers;

use App\Models\San;
use App\Services\LichSan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Trang chi tiết sân cho khách & người chưa đăng nhập: ảnh, giá, lịch 7 ngày. */
class SanCongKhaiController extends Controller
{
    public function show(Request $request, San $san, LichSan $lich): View
    {
        $hoSo = $san->chuSan->hoSoChuSan;
        abort_unless($san->trang_thai === 'hoat_dong' && $hoSo?->conHan(), 404);

        $tu = $this->ngayBatDau($request);
        $cungCoSo = San::where('co_so_id', $san->co_so_id)->where('trang_thai', 'hoat_dong')->orderBy('loai_san')->orderBy('ten')->get();
        $cungLoai = $san->cungLoaiCungCoSo();
        $gop = $cungLoai->count() > 1 && $request->query('gop', '1') !== '0';

        return view('san.show', [
            'san' => $san->load('anhSans', 'xaPhuong.tinhThanh', 'sanCons', 'coSo'),
            'hoSo' => $hoSo,
            'tu' => $tu,
            'cungCoSo' => $cungCoSo,
            'gop' => $gop,
            'soCungLoai' => $cungLoai->count(),
            'lich' => $gop ? $lich->tuanGop($cungLoai, $tu) : $lich->tuan($san, $tu),
        ]);
    }

    private function ngayBatDau(Request $request): Carbon
    {
        $tu = rescue(fn () => Carbon::parse((string) $request->query('tu', today()->toDateString()))->startOfDay(), today(), false);

        return $tu->lt(today()) ? today() : ($tu->gt(today()->addDays(28)) ? today()->addDays(28) : $tu);
    }
}
