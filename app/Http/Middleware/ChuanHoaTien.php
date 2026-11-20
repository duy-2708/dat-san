<?php

namespace App\Http\Middleware;

use App\Support\Tien;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Ô tiền gõ "100.000" / "100k" → 100000 trước khi kiểm tra dữ liệu (phòng khi trình duyệt tắt JS). */
class ChuanHoaTien
{
    private const COT = ['gia', 'gia_vang', 'gia_thang', 'tien_coc', 'so_tien', 'le_phi', 'tien_moi_nguoi', 'tien_coc_ca_nhan', 'gia_mac_dinh'];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::COT as $c) {
            $v = $request->input($c);
            if (is_string($v) && $v !== '') {
                $request->merge([$c => Tien::so($v) ?? $v]);
            }
        }

        return $next($request);
    }
}
