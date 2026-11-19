<?php

namespace App\Http\Controllers;

use App\Models\DatSan;
use App\Models\San;
use App\Services\DatSanService;
use App\Support\VietQr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Phía KHÁCH: giữ chỗ → trang quét QR cọc → theo dõi trạng thái / huỷ. */
class DatSanController extends Controller
{
    public function __construct(private DatSanService $datSan)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'san_id' => ['required', 'integer', 'exists:san,id'],
            'ngay' => ['required', 'date_format:Y-m-d'],
            'khung_gio_id' => ['required', 'integer'],
        ]);

        $don = $this->datSan->giuCho($request->user(), San::findOrFail($d['san_id']), $d['ngay'], (int) $d['khung_gio_id']);

        return redirect()->route('dat-san.thanh-toan', $don);
    }

    public function thanhToan(DatSan $datSan): View
    {
        Gate::authorize('view', $datSan);

        $datSan->load('san.chuSan.hoSoChuSan', 'san.xaPhuong.tinhThanh');
        $hoSo = $datSan->san->chuSan->hoSoChuSan;
        $qr = null;
        if ($datSan->trang_thai === 'cho_thanh_toan' && $hoSo?->ngan_hang_bin && $hoSo->so_tai_khoan) {
            $qr = VietQr::svg(VietQr::chuoi($hoSo->ngan_hang_bin, $hoSo->so_tai_khoan, $datSan->tien_coc, $datSan->ma_don));
        }

        return view('dat-san.thanh-toan', ['don' => $datSan, 'hoSo' => $hoSo, 'qr' => $qr]);
    }

    /** Trang thanh toán gọi mỗi 5 giây để tự chuyển sang "Đã đặt" khi chủ sân xác nhận. */
    public function trangThai(DatSan $datSan): JsonResponse
    {
        Gate::authorize('view', $datSan);

        return response()->json([
            'trang_thai' => $datSan->trang_thai,
            'giu_cho_den' => $datSan->giu_cho_den?->toIso8601String(),
        ]);
    }

    public function huy(Request $request, DatSan $datSan): RedirectResponse
    {
        Gate::authorize('huy', $datSan);

        $don = $this->datSan->huy($datSan, $request->user(), 'Khách tự huỷ');

        return redirect()->route('khach.tai-khoan')->with('thanh_cong', match ($don->xu_ly_coc) {
            'cho_hoan' => "Đã huỷ {$don->ma_don}. Chủ sân sẽ hoàn cọc cho bạn.",
            'mat_coc' => "Đã huỷ {$don->ma_don}. Huỷ sát giờ nên không được hoàn cọc.",
            default => "Đã huỷ {$don->ma_don}.",
        });
    }
}
