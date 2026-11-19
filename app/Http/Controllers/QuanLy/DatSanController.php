<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\DatSan;
use App\Models\San;
use App\Services\DatSanService;
use App\Services\LichSan;
use App\Support\ChuanHoa;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Phía SÂN: xem đơn, xác nhận cọc, gia hạn, đặt tại chỗ, huỷ, xác nhận đã hoàn cọc. */
class DatSanController extends Controller
{
    public function __construct(private DatSanService $datSan)
    {
    }

    public function index(Request $request): View
    {
        $this->datSan->nhaChoQuaHan();
        $chuSanId = $request->user()->idChuSanQuanLy();
        $ngay = rescue(fn () => Carbon::parse((string) $request->query('ngay', today()->toDateString())), today(), false);

        $base = DatSan::with('san', 'khach')->whereHas('san', fn ($q) => $q->where('chu_san_id', $chuSanId));

        return view('quan-ly.dat-san.index', [
            'ngay' => $ngay,
            'choCoc' => (clone $base)->where('trang_thai', 'cho_thanh_toan')->orderBy('giu_cho_den')->get(),
            'choHoan' => (clone $base)->where('xu_ly_coc', 'cho_hoan')->orderBy('huy_luc')->get(),
            'trongNgay' => (clone $base)->where('ngay', $ngay->toDateString())->where('trang_thai', 'da_dat')
                ->orderBy('gio_bat_dau')->get(),
            'cocKeoHoan' => \App\Models\GiaoDich::where('chu_san_id', $chuSanId)->where('xu_ly_hoan', 'cho_hoan')->with('nguoiTra')->get(),
            'cocKeo' => \App\Models\GiaoDich::where('chu_san_id', $chuSanId)->whereIn('loai', ['coc_keo_doi', 'coc_keo_le'])
                ->where('trang_thai', 'cho_thanh_toan')->with('nguoiTra')->latest('id')->get(),
            'keoXuLy' => \App\Models\Keo::with('datSan.san')->whereIn('trang_thai', ['tranh_chap', 'cho_xac_nhan', 'da_chot'])
                ->whereHas('datSan.san', fn ($q) => $q->where('chu_san_id', $chuSanId))->get()
                ->filter(fn ($k) => $k->trang_thai === 'tranh_chap' || $k->datSan->batDauLuc()->isPast()),
        ]);
    }

    public function lich(Request $request, San $san, LichSan $lich): View
    {
        Gate::authorize('view', $san);

        $tu = rescue(fn () => Carbon::parse((string) $request->query('tu', today()->toDateString()))->startOfDay(), today(), false);

        return view('quan-ly.san.lich', [
            'san' => $san,
            'tu' => $tu,
            'lich' => $lich->tuan($san, $tu, 7, chiTiet: true),
        ]);
    }

    public function datTaiCho(Request $request, San $san): RedirectResponse
    {
        Gate::authorize('view', $san); // chủ sân + nhân viên

        $request->merge(['sdt' => ChuanHoa::soDienThoai($request->input('sdt'))]);
        $d = $request->validate([
            'ngay' => ['required', 'date_format:Y-m-d'],
            'khung_gio_id' => ['required', 'integer'],
            'ten' => ['required', 'string', 'max:100'],
            'sdt' => ['required', 'regex:'.ChuanHoa::REGEX_SDT],
            'da_thu_coc' => ['nullable', 'boolean'],
            'ghi_chu' => ['nullable', 'string', 'max:255'],
        ], ['sdt.regex' => 'Số điện thoại không hợp lệ.']);

        $don = $this->datSan->datTaiCho($request->user(), $san, $d['ngay'], (int) $d['khung_gio_id'],
            $d['ten'], $d['sdt'], $request->boolean('da_thu_coc'), $d['ghi_chu'] ?? null);

        return back()->with('thanh_cong', "Đã đặt {$don->khungGioNhan()} cho {$don->ten_khach_tai_cho} ({$don->ma_don}).");
    }

    public function xacNhanCoc(Request $request, DatSan $datSan): RedirectResponse
    {
        Gate::authorize('quanLy', $datSan);
        $this->datSan->xacNhanCoc($datSan, $request->user());

        return back()->with('thanh_cong', "Đã xác nhận cọc {$datSan->ma_don}. Khách nhận email xác nhận.");
    }

    public function giaHan(DatSan $datSan): RedirectResponse
    {
        Gate::authorize('quanLy', $datSan);
        $don = $this->datSan->giaHanGiuCho($datSan);

        return back()->with('thanh_cong', "Đã giữ {$don->ma_don} tới ".$don->giu_cho_den->format('H:i').'.');
    }

    public function huy(Request $request, DatSan $datSan): RedirectResponse
    {
        Gate::authorize('huy', $datSan);
        $request->validate(['ly_do' => ['nullable', 'string', 'max:255']]);
        $don = $this->datSan->huy($datSan, $request->user(), $request->input('ly_do') ?: 'Chủ sân huỷ');

        return back()->with('thanh_cong', "Đã huỷ {$don->ma_don}.".($don->xu_ly_coc === 'cho_hoan' ? ' Nhớ chuyển trả cọc cho khách rồi bấm "Đã hoàn".' : ''));
    }

    public function daHoan(DatSan $datSan): RedirectResponse
    {
        Gate::authorize('xacNhanHoan', $datSan);
        $this->datSan->xacNhanDaHoan($datSan);

        return back()->with('thanh_cong', "Đã ghi nhận hoàn cọc {$datSan->ma_don}.");
    }
}
