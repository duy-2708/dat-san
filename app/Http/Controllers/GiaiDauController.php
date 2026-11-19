<?php

namespace App\Http\Controllers;

use App\Models\Doi;
use App\Models\GiaiDau;
use App\Services\GiaiDauService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GiaiDauController extends Controller
{
    public function __construct(private GiaiDauService $giai)
    {
    }

    public function index(): View
    {
        return view('giai-dau.index', [
            'giais' => GiaiDau::with('chuSan.hoSoChuSan')->withCount('doiDaDuyet')
                ->where('trang_thai', '!=', 'da_huy')
                ->orderByRaw("CASE trang_thai WHEN 'mo_dang_ky' THEN 0 WHEN 'dang_dien_ra' THEN 1 ELSE 2 END")
                ->latest('tu_ngay')->get(),
        ]);
    }

    public function show(Request $request, GiaiDau $giaiDau): View
    {
        $giaiDau->load(['chuSan.hoSoChuSan', 'doiThamGias.doi', 'doiThamGias.cauThus', 'tranDaus.doiNha', 'tranDaus.doiKhach', 'tranDaus.san']);

        return view('giai-dau.show', [
            'g' => $giaiDau,
            'bxh' => $this->giai->bangXepHang($giaiDau),
            'doiCuaToi' => $request->user()
                ? Doi::where('doi_truong_id', $request->user()->id)->with('dangChoi.user')->get() : collect(),
        ]);
    }

    public function dangKy(Request $request, GiaiDau $giaiDau): RedirectResponse
    {
        $d = $request->validate([
            'doi_id' => ['required', 'integer'],
            'cau_thu' => ['required', 'array', 'min:1'],
            'cau_thu.*' => ['integer'],
        ], ['cau_thu.required' => 'Chọn ít nhất 1 cầu thủ.']);

        $this->giai->dangKy($request->user(), $giaiDau, Doi::findOrFail($d['doi_id']), array_map('intval', $d['cau_thu']));

        return back()->with('thanh_cong', 'Đã đăng ký, chờ ban tổ chức duyệt.');
    }
}
