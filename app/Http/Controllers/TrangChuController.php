<?php

namespace App\Http\Controllers;

use App\Models\CoSo;
use App\Models\San;
use App\Models\TinhThanh;
use App\Models\XaPhuong;
use App\Services\DatSanService;
use App\Services\LichSan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Trang chủ: lọc Tỉnh/Thành → Xã/Phường + loại sân, mỗi sân kèm lịch HÔM NAY (trống / đã đặt). */
class TrangChuController extends Controller
{
    public function index(Request $request, LichSan $lich, DatSanService $datSan): View
    {
        $loc = $request->validate([
            'tinh' => ['nullable', 'integer'],
            'xa' => ['nullable', 'integer'],
            'loai' => ['nullable', 'in:5,7,11'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $locSan = fn ($q) => $q->where('trang_thai', 'hoat_dong')
            ->whereHas('chuSan', fn ($u) => $u->where('trang_thai', 'hoat_dong')
                ->whereHas('hoSoChuSan', fn ($h) => \App\Models\HoSoChuSan::locConHan($h)))
            ->when($loc['loai'] ?? null, fn ($w, $l) => $w->where('loai_san', $l));

        $coSos = CoSo::query()
            ->with(['xaPhuong.tinhThanh', 'sans' => fn ($q) => $locSan($q)->with('anhSans')])
            ->whereHas('sans', $locSan)
            ->when($loc['tinh'] ?? null, fn ($q, $t) => $q->whereHas('xaPhuong', fn ($x) => $x->where('tinh_thanh_id', $t)))
            ->when($loc['xa'] ?? null, fn ($q, $x) => $q->where('xa_phuong_id', $x))
            ->when($loc['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('ten', 'like', "%{$t}%")
                ->orWhereHas('sans', fn ($s) => $s->where('ten', 'like', "%{$t}%")->orWhere('biet_danh', 'like', "%{$t}%"))))
            ->orderBy('ten')
            ->paginate(12)
            ->withQueryString();

        $datSan->nhaChoQuaHan();
        $homNay = [];
        foreach ($coSos as $c) {
            foreach ($c->sans->groupBy('loai_san') as $loai => $nhom) {
                $homNay[$c->id][$loai] = $nhom->count() > 1
                    ? $lich->tuanGop($nhom, today(), 1)[0]['khungs']
                    : $lich->tuan($nhom->first(), today(), 1)[0]['khungs'];
            }
        }

        $keoMoi = \App\Models\Keo::with(['san.xaPhuong', 'datSan.san.xaPhuong', 'khungGio', 'doiA', 'daiDienA', 'nguoiTao'])
            ->where('trang_thai', 'dang_tim')->where('cong_khai', true)
            ->where(fn ($q) => $q->where('ngay', '>=', today()->toDateString())->orWhereHas('datSan', fn ($d) => $d->where('ngay', '>=', today()->toDateString())))
            ->latest('id')->limit(200)->get()
            ->filter(fn ($k) => $k->batDauLuc()->isFuture()
                && (! ($loc['tinh'] ?? null) || (int) $k->sanThuc()->xaPhuong->tinh_thanh_id === (int) $loc['tinh']))
            ->values();

        return view('trang-chu', [
            'soKeoMo' => $keoMoi->count(),
            'keoMoi' => $keoMoi->take(6),
            'coSos' => $coSos,
            'homNay' => $homNay,
            'loc' => $loc,
            'tinhThanhs' => TinhThanh::orderBy('thu_tu')->get(),
            'xaPhuongs' => ($loc['tinh'] ?? null) ? XaPhuong::where('tinh_thanh_id', $loc['tinh'])->orderBy('ten')->get() : collect(),
        ]);
    }
}
