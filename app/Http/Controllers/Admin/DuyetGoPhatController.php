<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\YeuCauGoPhat;
use App\Services\KeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DuyetGoPhatController extends Controller
{
    public function index(): View
    {
        return view('admin.duyet-go-phat', [
            'choDuyet' => YeuCauGoPhat::with(['chuSan.hoSoChuSan', 'user', 'thamGia.keo'])->where('trang_thai', 'cho_duyet')->oldest()->get(),
            'daXuLy' => YeuCauGoPhat::with(['chuSan', 'user', 'admin'])->where('trang_thai', '!=', 'cho_duyet')->latest('xu_ly_luc')->limit(30)->get(),
        ]);
    }

    public function xuLy(Request $request, YeuCauGoPhat $yeuCau, KeoService $keo): RedirectResponse
    {
        $d = $request->validate(['dong_y' => ['required', 'boolean'], 'ghi_chu_admin' => ['nullable', 'string', 'max:300']]);
        if ($yeuCau->trang_thai !== 'cho_duyet') {
            return back()->withErrors(['yeu_cau' => 'Đề nghị này đã được xử lý.']);
        }

        DB::transaction(function () use ($d, $yeuCau, $keo, $request) {
            if ($d['dong_y']) {
                $yeuCau->loai === 'mien_phat'
                    ? $keo->mienPhat($yeuCau->thamGia, $yeuCau->chuSan)
                    : $keo->goHanChe($yeuCau->user);
            }
            $yeuCau->forceFill([
                'trang_thai' => $d['dong_y'] ? 'da_duyet' : 'tu_choi', 'admin_id' => $request->user()->id,
                'ghi_chu_admin' => $d['ghi_chu_admin'] ?? null, 'xu_ly_luc' => now(),
            ])->save();
            \App\Support\BaoTin::gui($yeuCau->chu_san_id, $d['dong_y'] ? 'Admin đã duyệt đề nghị gỡ phạt' : 'Admin từ chối đề nghị gỡ phạt',
                YeuCauGoPhat::LOAI[$yeuCau->loai].' cho '.$yeuCau->user->name.(($d['ghi_chu_admin'] ?? null) ? ' — '.$d['ghi_chu_admin'] : '').'.', null, $d['dong_y'] ? 'check-circle' : 'x-circle');
        });

        return back()->with('thanh_cong', ($d['dong_y'] ? 'Đã duyệt: ' : 'Đã từ chối: ').YeuCauGoPhat::LOAI[$yeuCau->loai].' cho '.$yeuCau->user->name.'.');
    }
}
