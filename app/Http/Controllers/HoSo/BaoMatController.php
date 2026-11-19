<?php

namespace App\Http\Controllers\HoSo;

use App\Http\Controllers\Controller;
use App\Models\NhatKy;
use Illuminate\Http\Request;

/**
 * Trang "Bảo mật tài khoản": bật/tắt xác minh 2 bước (Google Authenticator), mã dự phòng, lịch sử đăng nhập.
 * Các nút gọi route của Fortify (two-factor.enable / confirm / disable / regenerate-recovery-codes).
 */
class BaoMatController extends Controller
{
    public function show(Request $request)
    {
        $u = $request->user();

        // Có mật khẩu → phải nhập lại mật khẩu (3 giờ/lần) mới xem được mã QR & mã dự phòng
        $xacNhanLuc = (int) $request->session()->get('auth.password_confirmed_at', 0);
        if ($u->password && time() - $xacNhanLuc > config('auth.password_timeout', 10800)) {
            return redirect()->guest(route('password.confirm'));
        }

        return view('ho-so.bao-mat', [
            'u' => $u,
            'lichSu' => NhatKy::where('user_id', $u->id)->whereIn('hanh_dong', ['dang_nhap', 'dang_nhap_sai', 'bi_chan_dang_nhap', 'doi_mat_khau'])
                ->latest('id')->limit(10)->get(),
        ]);
    }
}
