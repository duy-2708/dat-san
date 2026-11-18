<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dùng trong route: ->middleware('vai_tro:chu_san,nhan_vien')
 * Đây là lớp chặn THÔ theo vai trò. Quyền trên TỪNG bản ghi (sân này có phải của mình không)
 * do Policy kiểm tra.
 */
class KiemTraVaiTro
{
    public function handle(Request $request, Closure $next, string ...$vaiTro): Response
    {
        $user = $request->user();

        abort_unless($user && $user->coVaiTro(...$vaiTro), 403, 'Bạn không có quyền truy cập trang này.');

        return $next($request);
    }
}
