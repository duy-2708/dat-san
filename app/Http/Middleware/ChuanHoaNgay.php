<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChuanHoaNgay
{
    private const MAU = '#^(\d{1,2})/(\d{1,2})/(\d{4})(?:\s+(\d{1,2}):(\d{2}))?$#';

    public function handle(Request $request, Closure $next): Response
    {
        foreach ([$request->query, $request->request] as $bag) {
            foreach ($bag->all() as $k => $v) {
                if (is_string($v) && ($moi = self::doi($v)) !== null) {
                    $bag->set($k, $moi);
                }
            }
        }

        return $next($request);
    }

    public static function doi(string $v): ?string
    {
        if (! preg_match(self::MAU, trim($v), $m) || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }
        $ngay = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);

        return isset($m[4]) && $m[4] !== '' ? $ngay.sprintf(' %02d:%02d', $m[4], $m[5]) : $ngay;
    }
}
