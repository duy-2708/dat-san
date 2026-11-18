<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Tài khoản tạo qua Google chưa có SĐT → bắt bổ sung trước khi dùng (1 SĐT = 1 tài khoản). */
class YeuCauSoDienThoai
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->so_dien_thoai && ! $request->routeIs('ho-so.so-dien-thoai*', 'logout')) {
            return redirect()->route('ho-so.so-dien-thoai');
        }

        return $next($request);
    }
}
