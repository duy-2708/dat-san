<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admin nắm quyền cả hệ thống → bắt buộc bật xác minh 2 bước (khi BAT_BUOC_2FA_ADMIN=true). */
class YeuCau2FAAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (config('bao_mat.admin_2fa') && $u?->laAdmin() && is_null($u->two_factor_confirmed_at)) {
            return redirect()->route('bao-mat')
                ->with('loi', 'Tài khoản admin phải bật xác minh 2 bước trước khi vào trang quản trị.');
        }

        return $next($request);
    }
}
