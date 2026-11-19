<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chống spam / dò quét: giới hạn số lần GỬI (POST/PATCH/DELETE).
 * - Đăng ký, quên mật khẩu, gửi lại mail xác thực: 5 lần / 10 phút / IP.
 * - Các thao tác khác: 40 lần / phút / tài khoản (chưa đăng nhập thì theo IP).
 * Đăng nhập sai đã có giới hạn riêng của Fortify (5 lần/phút/email+IP). Webhook có throttle riêng.
 */
class GioiHanThaoTac
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('bao_mat.gioi_han') || $request->isMethodSafe() || $request->is('webhook/*')) {
            return $next($request);
        }

        $nhayCam = $request->is('register', 'forgot-password', 'reset-password', 'email/verification-notification');
        [$khoa, $toiDa, $giay] = $nhayCam
            ? ['nhay|'.$request->ip(), config('bao_mat.nhay_cam_10_phut', 5), 600]
            : ['ghi|'.($request->user()?->id ? 'u'.$request->user()->id : $request->ip()), config('bao_mat.gui_moi_phut', 40), 60];

        if (RateLimiter::tooManyAttempts($khoa, $toiDa)) {
            $con = RateLimiter::availableIn($khoa);
            throw new ThrottleRequestsException('Bạn thao tác quá nhanh. Thử lại sau '.$con.' giây.', null, ['Retry-After' => $con]);
        }
        RateLimiter::hit($khoa, $giay);

        return $next($request);
    }
}
