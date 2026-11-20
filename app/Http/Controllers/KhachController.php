<?php

namespace App\Http\Controllers;

use App\Models\DatSan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KhachController extends Controller
{
    public function taiKhoan(Request $request): View
    {
        $don = DatSan::with('san.xaPhuong.tinhThanh')
            ->where('khach_id', $request->user()->id)
            ->orderByDesc('ngay')->orderByDesc('gio_bat_dau')
            ->limit(50)
            ->get();

        $sapToi = $don->filter(fn ($d) => $d->trang_thai !== 'da_huy' && $d->ketThucLuc()->isFuture())->sortBy(fn ($d) => $d->batDauLuc());
        $daQua = $don->reject(fn ($d) => $sapToi->contains('id', $d->id));

        $uid = $request->user()->id;
        $keoCuaToi = \App\Models\Keo::with(['san', 'datSan.san', 'khungGio', 'doiA', 'doiB'])
            ->whereIn('trang_thai', ['dang_tim', 'cho_coc', 'da_chot', 'cho_xac_nhan', 'tranh_chap'])
            ->where(fn ($q) => $q->where('dai_dien_a_id', $uid)->orWhere('nguoi_tao_id', $uid)->orWhere('dai_dien_b_id', $uid)
                ->orWhereHas('xinKeos', fn ($x) => $x->where('user_id', $uid)->where('trang_thai', 'cho_duyet')))
            ->latest('id')->limit(20)->get();

        return view('khach.tai-khoan', compact('sapToi', 'daQua', 'keoCuaToi'));
    }

    public function thanhToan(Request $request, \App\Services\ViecCanLam $viec): View
    {
        $u = $request->user();

        return view('khach.thanh-toan', [
            'viec' => $viec->cua($u),
            'lichSu' => \App\Models\GiaoDich::with('datSan.san')->where('nguoi_tra_id', $u->id)
                ->where('trang_thai', '!=', 'cho_thanh_toan')->latest('id')->paginate(20),
            'donDaDat' => DatSan::with('san')->where('khach_id', $u->id)->where('trang_thai', 'da_dat')
                ->orderByDesc('ngay')->limit(20)->get(),
        ]);
    }
}
