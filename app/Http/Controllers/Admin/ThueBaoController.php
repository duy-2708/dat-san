<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HoSoChuSan;
use App\Models\ThueBao;
use App\Services\ThueBaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin: đơn thuê bao (xác nhận tiền về / huỷ) + số liệu doanh thu. */
class ThueBaoController extends Controller
{
    public function index(Request $request): View
    {
        $loc = $request->query('trang_thai', 'cho_thanh_toan');
        $daTra = ThueBao::where('trang_thai', 'da_thanh_toan');

        return view('admin.thue-bao.index', [
            'loc' => $loc,
            'dons' => ThueBao::with(['chuSan.hoSoChuSan', 'goiThueBao'])
                ->when($loc !== 'tat_ca', fn ($q) => $q->where('trang_thai', $loc))
                ->latest('id')->paginate(30)->withQueryString(),
            'thongKe' => [
                'thang_nay' => (int) (clone $daTra)->where('xac_nhan_luc', '>=', now()->startOfMonth())->sum('so_tien'),
                'nam_nay' => (int) (clone $daTra)->where('xac_nhan_luc', '>=', now()->startOfYear())->sum('so_tien'),
                'cho' => ThueBao::where('trang_thai', 'cho_thanh_toan')->count(),
                'con_han' => HoSoChuSan::where('het_han_luc', '>', now())->count(),
                'sap_het' => HoSoChuSan::whereBetween('het_han_luc', [now(), now()->addDays(7)])->count(),
                'het_han' => HoSoChuSan::where(fn ($q) => $q->whereNull('het_han_luc')->orWhere('het_han_luc', '<=', now()))->count(),
            ],
        ]);
    }

    public function xacNhan(Request $request, ThueBao $thueBao, ThueBaoService $sv): RedirectResponse
    {
        $sv->xacNhan($thueBao, $request->user());

        return back()->with('thanh_cong', "Đã xác nhận đơn #{$thueBao->id}, gia hạn cho {$thueBao->chuSan->name}.");
    }

    public function huy(ThueBao $thueBao, ThueBaoService $sv): RedirectResponse
    {
        $sv->huy($thueBao);

        return back()->with('thanh_cong', "Đã huỷ đơn #{$thueBao->id}.");
    }
}
