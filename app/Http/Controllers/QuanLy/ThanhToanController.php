<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Support\VietQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Chủ sân cài đặt nhận cọc. Giai đoạn 3: cách A (VietQR tài khoản riêng). Cách B (PayOS) mở ở giai đoạn 5. */
class ThanhToanController extends Controller
{
    public function edit(Request $request): View
    {
        $hoSo = $request->user()->hoSoChuSan;
        $qrThu = $hoSo->ngan_hang_bin && $hoSo->so_tai_khoan
            ? VietQr::svg(VietQr::chuoi($hoSo->ngan_hang_bin, $hoSo->so_tai_khoan, 10000, 'THU QR'), 200)
            : null;

        return view('quan-ly.thanh-toan', compact('hoSo', 'qrThu'));
    }

    public function update(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'ngan_hang_bin' => ['required', Rule::in(array_keys(VietQr::NGAN_HANG))],
            'so_tai_khoan' => ['required', 'regex:/^[0-9]{6,19}$/'],
            'ten_chu_tai_khoan' => ['required', 'string', 'max:100'],
            'sdt_lien_he' => ['nullable', 'string', 'max:15'],
            'phut_giu_cho' => ['required', 'integer', 'min:5', 'max:60'],
            'gio_huy_hoan_coc' => ['required', 'integer', 'min:0', 'max:168'],
        ], ['so_tai_khoan.regex' => 'Số tài khoản chỉ gồm 6–19 chữ số.']);

        $d['ten_chu_tai_khoan'] = VietQr::tenKhongDau($d['ten_chu_tai_khoan']);
        $request->user()->hoSoChuSan->fill($d)->save();

        return back()->with('thanh_cong', 'Đã lưu. Hãy quét thử mã QR 10.000đ bằng app ngân hàng để kiểm tra đúng tên chủ tài khoản (KHÔNG cần chuyển).');
    }
}
