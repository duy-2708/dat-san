<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Header bảo mật cho mọi trang: chống nhúng iframe lừa bấm (clickjacking), đoán sai kiểu file, lộ đường dẫn. */
class TieuDeBaoMat
{
    public function handle(Request $request, Closure $next): Response
    {
        $res = $next($request);
        $h = $res->headers;
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=()');
        $h->set('Content-Security-Policy', "frame-ancestors 'self'; object-src 'none'; base-uri 'self'");
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        $h->remove('X-Powered-By');

        return $res;
    }
}
