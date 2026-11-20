<?php

namespace App\Http\Controllers;

use App\Models\DatSan;
use App\Models\GiaoDich;
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
            'thoi_luong' => ['nullable', 'integer'],
            'bat_dau' => ['nullable', 'date_format:H:i'],
        ]);
        $batDau = isset($d['bat_dau']) ? \App\Support\ThoiGian::phut($d['bat_dau']) : null;

        $san = San::findOrFail($d['san_id']);
        $thoiLuong = isset($d['thoi_luong']) ? (int) $d['thoi_luong'] : null;
        $khung = \App\Models\KhungGio::where('san_id', $san->id)->find((int) $d['khung_gio_id']);
        $ungVien = $request->boolean('tu_xep') && $khung ? $san->cungLoaiCungCoSo() : collect([$san]);
        $loiCuoi = null;
        $don = null;
        foreach ($ungVien as $s) {
            $kId = $s->id === $san->id ? (int) $d['khung_gio_id']
                : \App\Models\KhungGio::where('san_id', $s->id)->where('loai_ngay', $khung->loai_ngay)->where('gio_bat_dau', $khung->gio_bat_dau)->value('id');
            if (! $kId) {
                continue;
            }
            try {
                $don = $this->datSan->giuCho($request->user(), $s, $d['ngay'], (int) $kId, $thoiLuong, $batDau);
                break;
            } catch (\Illuminate\Validation\ValidationException $e) {
                $loiCuoi = $e;
            }
        }
        if (! $don) {
            throw $loiCuoi ?? \Illuminate\Validation\ValidationException::withMessages(['dat_san' => 'Khung giờ này đã hết sân trống.']);
        }

        return redirect()->route('dat-san.thanh-toan', $don);
    }

    public function daChuyen(Request $request, DatSan $datSan): RedirectResponse
    {
        $this->datSan->baoDaChuyen($datSan, $request->user());

        return back()->with('thanh_cong', 'Đã báo chủ sân. Chỗ được giữ thêm 2 tiếng trong lúc chủ sân kiểm tra.');
    }

    public function thanhToan(DatSan $datSan): View
    {
        Gate::authorize('view', $datSan);

        $datSan->load('san.chuSan.hoSoChuSan', 'san.xaPhuong.tinhThanh');
        $hoSo = $datSan->san->chuSan->hoSoChuSan;
        $gd = GiaoDich::where('dat_san_id', $datSan->id)->latest('id')->first();
        $qr = null;
        if ($datSan->trang_thai === 'cho_thanh_toan') {
            if ($gd?->phuong_thuc === 'payos' && ! empty($gd->du_lieu_cong['qr'])) {
                $qr = VietQr::svg($gd->du_lieu_cong['qr']);                     // QR do PayOS cấp (tự xác nhận)
            } elseif ($hoSo?->ngan_hang_bin && $hoSo->so_tai_khoan) {
                $qr = VietQr::svg(VietQr::chuoi($hoSo->ngan_hang_bin, $hoSo->so_tai_khoan, $datSan->tien_coc, $datSan->ma_don));
            }
        }

        return view('dat-san.thanh-toan', ['don' => $datSan, 'hoSo' => $hoSo, 'qr' => $qr, 'gd' => $gd]);
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
