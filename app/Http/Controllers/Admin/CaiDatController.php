<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CaiDat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin: thời gian miễn phí + tài khoản ngân hàng nhận tiền thuê bao. */
class CaiDatController extends Controller
{
    public const KHOA = ['mien_phi_den', 'tk_bin', 'tk_so', 'tk_ten', 'sdt_ho_tro'];

    public function edit(): View
    {
        return view('admin.cai-dat', ['cd' => collect(self::KHOA)->mapWithKeys(fn ($k) => [$k => CaiDat::lay($k)])]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge(['tk_bin' => \App\Support\VietQr::timBin($request->input('tk_bin'))]);
        $d = $request->validate([
            'mien_phi_den' => ['nullable', 'date'],
            'tk_bin' => ['nullable', \Illuminate\Validation\Rule::in(array_map('strval', array_keys(\App\Support\VietQr::NGAN_HANG)))],
            'tk_so' => ['nullable', 'regex:/^[0-9A-Za-z]{4,30}$/'],
            'tk_ten' => ['nullable', 'string', 'max:100'],
            'sdt_ho_tro' => ['nullable', 'string', 'max:20'],
        ], ['tk_bin.in' => 'Không tìm thấy ngân hàng này, hãy chọn trong danh sách gợi ý.']);
        $d['tk_ten'] = isset($d['tk_ten']) ? mb_strtoupper($d['tk_ten']) : null;
        CaiDat::dat(array_merge(array_fill_keys(self::KHOA, null), $d));

        return back()->with('thanh_cong', 'Đã lưu cài đặt.');
    }
}
