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

        return view('khach.tai-khoan', compact('sapToi', 'daQua'));
    }
}
