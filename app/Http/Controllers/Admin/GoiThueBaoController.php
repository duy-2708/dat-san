<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoiThueBao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin quản lý bảng giá gói. Gói đã có người dùng không xoá, chỉ "ngừng bán". */
class GoiThueBaoController extends Controller
{
    public function index(): View
    {
        return view('admin.goi.index', ['gois' => GoiThueBao::withCount('hoSoChuSans')->orderBy('gia_thang')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $g = new GoiThueBao;
        $g->forceFill($this->duLieu($request))->save();

        return back()->with('thanh_cong', "Đã thêm gói {$g->ten}.");
    }

    public function update(Request $request, GoiThueBao $goi): RedirectResponse
    {
        $goi->forceFill($this->duLieu($request, $goi))->save();

        return back()->with('thanh_cong', "Đã lưu gói {$goi->ten}.");
    }

    private function duLieu(Request $request, ?GoiThueBao $goi = null): array
    {
        $d = $request->validate([
            'ten' => ['required', 'string', 'max:100', 'unique:goi_thue_bao,ten'.($goi ? ','.$goi->id : '')],
            'gia_thang' => ['required', 'integer', 'min:0', 'max:100000000'],
            'so_san_toi_da' => ['required', 'integer', 'min:1', 'max:1000'],
            'so_nhan_vien_toi_da' => ['required', 'integer', 'min:0', 'max:1000'],
            'mo_ta' => ['nullable', 'string', 'max:500'],
        ]);
        $d['cho_phep_cong_tu_dong'] = $request->boolean('cho_phep_cong_tu_dong');
        $d['dang_ban'] = $request->boolean('dang_ban');

        return $d;
    }
}
