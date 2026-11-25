<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\ChuoiCoDinh;
use App\Models\San;
use App\Services\DatSanService;
use App\Support\ChuanHoa;
use App\Support\ThoiGian;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CoDinhController extends Controller
{
    public function __construct(private DatSanService $datSan)
    {
    }

    public function index(Request $request): View
    {
        $chuSanId = $request->user()->idChuSanQuanLy();
        $sans = San::where('chu_san_id', $chuSanId)->orderBy('ten')->get();
        $chuoi = ChuoiCoDinh::with('san')->whereHas('san', fn ($q) => $q->where('chu_san_id', $chuSanId))
            ->where(fn ($q) => $q->whereNull('den_ngay')->orWhere('den_ngay', '>=', today()->toDateString()))
            ->latest('id')->get();

        return view('quan-ly.co-dinh.index', compact('sans', 'chuoi'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['sdt' => ChuanHoa::soDienThoai($request->input('sdt'))]);
        $d = $request->validate([
            'san_id' => ['required', 'integer'],
            'thu' => ['required', 'integer', 'between:0,6'],
            'gio_bat_dau' => ['required', 'date_format:H:i'],
            'gio_ket_thuc' => ['required', 'date_format:H:i'],
            'ten' => ['required', 'string', 'max:100'],
            'sdt' => ['required', 'regex:'.ChuanHoa::REGEX_SDT],
            'tu_ngay' => ['required', 'date_format:Y-m-d'],
            'den_ngay' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tu_ngay'],
        ], ['sdt.regex' => 'Số điện thoại không hợp lệ.']);

        $san = San::findOrFail($d['san_id']);
        Gate::authorize('view', $san);

        $ket = $this->datSan->taoChuoiCoDinh($request->user(), $san, (int) $d['thu'],
            ThoiGian::phut($d['gio_bat_dau']), ThoiGian::phut($d['gio_ket_thuc']),
            $d['ten'], $d['sdt'], $d['tu_ngay'], $d['den_ngay'] ?? null);

        $den = ($d['den_ngay'] ?? null) ? Carbon::parse($d['den_ngay'])->format('d/m/Y') : 'hết năm';
        $msg = "Đã đặt cố định {$ket['so']} buổi cho {$d['ten']} đến {$den}.";
        if ($ket['bo_qua']) {
            $msg .= ' Bỏ qua các buổi trùng lịch hoặc ngoài khung giá: '.implode(', ', $ket['bo_qua']).'.';
        }

        return redirect()->route('quan-ly.co-dinh.index')->with('thanh_cong', $msg);
    }

    public function huyKhoang(Request $request, ChuoiCoDinh $chuoi): RedirectResponse
    {
        abort_unless((int) $chuoi->san->chu_san_id === $request->user()->idChuSanQuanLy(), 403);
        $d = $request->validate([
            'tu' => ['required', 'date_format:Y-m-d'],
            'den' => ['required', 'date_format:Y-m-d', 'after_or_equal:tu'],
        ]);
        $ket = $this->datSan->huyChuoiTrongKhoang($chuoi, $request->user(), $d['tu'], $d['den']);

        $msg = "Đã bỏ {$ket['huy']} buổi trong khoảng đã chọn.";
        if ($ket['giu'] > 0) {
            $msg .= " {$ket['giu']} buổi đã thu tiền sân nên được giữ lại.";
        }

        return back()->with('thanh_cong', $msg);
    }

    public function xoa(Request $request, ChuoiCoDinh $chuoi): RedirectResponse
    {
        abort_unless((int) $chuoi->san->chu_san_id === $request->user()->idChuSanQuanLy(), 403);
        $ket = $this->datSan->huyChuoiTrongKhoang($chuoi, $request->user(), today()->toDateString(), today()->addYears(10)->toDateString());
        $chuoi->forceFill(['den_ngay' => today()->subDay()->toDateString()])->save();

        $msg = "Đã xóa lịch cố định {$chuoi->ten}, huỷ {$ket['huy']} buổi sắp tới.";
        if ($ket['giu'] > 0) {
            $msg .= " {$ket['giu']} buổi đã thu tiền sân được giữ lại trong thống kê.";
        }

        return back()->with('thanh_cong', $msg);
    }
}
