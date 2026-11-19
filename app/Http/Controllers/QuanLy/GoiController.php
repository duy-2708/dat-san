<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\CaiDat;
use App\Models\GoiThueBao;
use App\Models\ThueBao;
use App\Services\ThueBaoService;
use App\Support\VietQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Chủ sân: xem gói hiện tại, mua / gia hạn gói, trả tiền qua VietQR cho admin. */
class GoiController extends Controller
{
    public function index(Request $request): View
    {
        $u = $request->user();

        return view('quan-ly.goi.index', [
            'hoSo' => $u->hoSoChuSan()->with('goiThueBao')->first(),
            'gois' => GoiThueBao::where('dang_ban', true)->orderBy('gia_thang')->get(),
            'lichSu' => ThueBao::with('goiThueBao')->where('chu_san_id', $u->id)->latest('id')->limit(20)->get(),
            'mienPhiDen' => CaiDat::dangMienPhi() ? CaiDat::mienPhiDen() : null,
            'soSan' => $u->sans()->count(),
            'soNhanVien' => $u->nhanViens()->count(),
        ]);
    }

    public function store(Request $request, ThueBaoService $sv): RedirectResponse
    {
        $d = $request->validate([
            'goi_thue_bao_id' => ['required', 'exists:goi_thue_bao,id'],
            'so_thang' => ['required', 'integer'],
        ]);
        $tb = $sv->taoDon($request->user(), GoiThueBao::findOrFail($d['goi_thue_bao_id']), (int) $d['so_thang']);

        return redirect()->route('quan-ly.goi.show', $tb);
    }

    public function show(Request $request, ThueBao $thueBao): View
    {
        abort_unless($thueBao->chu_san_id === $request->user()->id, 403);
        $bin = CaiDat::lay('tk_bin');
        $so = CaiDat::lay('tk_so');
        $qr = $thueBao->trang_thai === 'cho_thanh_toan' && $bin && $so
            ? VietQr::svg(VietQr::chuoi($bin, $so, (int) $thueBao->so_tien, $thueBao->noiDungChuyenKhoan()))
            : null;

        return view('quan-ly.goi.thanh-toan', [
            'tb' => $thueBao->load('goiThueBao'), 'qr' => $qr, 'bin' => $bin, 'so' => $so, 'ten' => CaiDat::lay('tk_ten'),
            'sdtAdmin' => CaiDat::lay('sdt_ho_tro'),
        ]);
    }

    public function huy(Request $request, ThueBao $thueBao, ThueBaoService $sv): RedirectResponse
    {
        abort_unless($thueBao->chu_san_id === $request->user()->id, 403);
        $sv->huy($thueBao);

        return redirect()->route('quan-ly.goi.index')->with('thanh_cong', 'Đã huỷ đơn gia hạn.');
    }
}
