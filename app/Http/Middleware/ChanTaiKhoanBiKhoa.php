<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chạy ở MỌI request web: nếu tài khoản đang đăng nhập bị khoá (hoặc chủ sân của nhân viên bị khoá)
 * thì đăng xuất ngay — không đợi phiên hết hạn.
 */
class ChanTaiKhoanBiKhoa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->biKhoa()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Tài khoản đã bị khoá. Vui lòng liên hệ quản trị viên.']);
        }

        return $next($request);
    }
}
