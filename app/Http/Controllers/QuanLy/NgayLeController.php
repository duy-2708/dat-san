<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\NgayLe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Ngày lễ chung (admin nhập, chỉ xem) + ngày riêng của chủ sân (VD ngày khai trương, giải đấu). */
class NgayLeController extends Controller
{
    public function index(Request $request): View
    {
        $tu = now()->startOfYear()->toDateString();

        return view('quan-ly.ngay-le.index', [
            'ngayChung' => NgayLe::whereNull('chu_san_id')->where('ngay', '>=', $tu)->orderBy('ngay')->get(),
            'ngayRieng' => NgayLe::where('chu_san_id', $request->user()->id)->where('ngay', '>=', $tu)->orderBy('ngay')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $chuSanId = $request->user()->id;

        $d = $request->validate([
            'ngay' => ['required', 'date', 'after_or_equal:today',
                Rule::unique('ngay_le', 'ngay')->where('chu_san_id', $chuSanId)],
            'ten' => ['required', 'string', 'max:100'],
        ], ['ngay.unique' => 'Ngày này đã có trong danh sách.']);

        $le = new NgayLe($d);
        $le->chu_san_id = $chuSanId;
        $le->save();

        return back()->with('thanh_cong', 'Đã thêm ngày áp giá lễ.');
    }

    public function destroy(Request $request, NgayLe $ngayLe): RedirectResponse
    {
        abort_unless($ngayLe->chu_san_id !== null && (int) $ngayLe->chu_san_id === $request->user()->id, 403); // không xoá được ngày chung / ngày của chủ khác

        $ngayLe->delete();

        return back()->with('thanh_cong', 'Đã xoá.');
    }
}
