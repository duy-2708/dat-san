<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Khu quản lý: chủ sân hết hạn gói (và đã hết thời gian miễn phí) chỉ còn vào được trang Gói dịch vụ
 * và xem danh sách đơn. Sân của họ đồng thời bị ẩn khỏi trang chủ và không nhận đặt (HoSoChuSan::conHan).
 */
class YeuCauConHanGoi
{
    private const CHO_PHEP = ['quan-ly.goi.*', 'quan-ly.tong-quan', 'quan-ly.dat-san.index'];

    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        $hoSo = $u?->laChuSan() ? $u->hoSoChuSan : ($u?->laNhanVien() ? $u->chuSan?->hoSoChuSan : null);

        if ($hoSo && ! $hoSo->conHan() && ! $request->routeIs(...self::CHO_PHEP)) {
            if ($u->laChuSan()) {
                return redirect()->route('quan-ly.goi.index')
                    ->withErrors(['thue_bao' => 'Gói dịch vụ đã hết hạn. Gia hạn để tiếp tục quản lý sân và nhận đặt sân.']);
            }

            return redirect()->route('quan-ly.tong-quan')
                ->withErrors(['thue_bao' => 'Gói dịch vụ của cơ sở đã hết hạn. Báo chủ sân gia hạn.']);
        }

        return $next($request);
    }
}
