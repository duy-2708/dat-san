<?php

namespace App\Http\Middleware;

use App\Models\NhatKy;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ghi lại mọi thao tác GHI của người đã đăng nhập (ai, làm gì, trên đối tượng nào, IP, kết quả).
 * Không ghi nội dung form (tránh lưu mật khẩu, số tài khoản).
 */
class GhiNhatKy
{
    public function handle(Request $request, Closure $next): Response
    {
        $res = $next($request);

        $u = $request->user();
        if ($u && ! $request->isMethodSafe() && ! $request->routeIs('logout', 'login.store', 'two-factor.login.store')) {
            $route = $request->route();
            $doiTuong = collect($route?->parameters() ?? [])
                ->map(fn ($v, $k) => $k.'#'.($v instanceof Model ? $v->getKey() : (is_scalar($v) ? $v : '?')))
                ->implode(' ');
            rescue(fn () => NhatKy::ghi(
                $route?->getName() ?? $request->method().' '.$request->path(),
                trim($doiTuong.' · HTTP '.$res->getStatusCode()),
                $u->id,
            ), report: false);
        }

        return $res;
    }
}
