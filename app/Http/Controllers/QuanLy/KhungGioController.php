<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\KhungGio;
use App\Models\San;
use App\Services\BangGia;
use App\Support\ThoiGian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KhungGioController extends Controller
{
    public function __construct(private BangGia $bangGia)
    {
    }

    public function index(San $san): View
    {
        Gate::authorize('view', $san);

        return view('quan-ly.san.khung-gio', [
            'san' => $san,
            'theoLoai' => $san->khungGios()->get()->groupBy('loai_ngay'),
            'cacMoc' => ThoiGian::cacMoc(ThoiGian::phut($san->gio_mo_cua), ThoiGian::phut($san->gio_dong_cua)),
        ]);
    }

    public function store(Request $request, San $san): RedirectResponse
    {
        Gate::authorize('update', $san);

        $d = $this->kiemTra($request, $san);

        $san->khungGios()->create($d);

        return back()->with('thanh_cong', 'Đã thêm khung giờ.')->withFragment($d['loai_ngay']);
    }

    public function update(Request $request, KhungGio $khungGio): RedirectResponse
    {
        $san = $khungGio->san;
        Gate::authorize('update', $san);

        $d = $this->kiemTra($request, $san, $khungGio);
        $khungGio->update($d);

        return back()->with('thanh_cong', 'Đã cập nhật khung '.$khungGio->nhan().'.')->withFragment($d['loai_ngay']);
    }

    public function destroy(KhungGio $khungGio): RedirectResponse
    {
        Gate::authorize('update', $khungGio->san);

        $khungGio->delete();

        return back()->with('thanh_cong', 'Đã xoá khung giờ.');
    }

    public function taoNhanh(Request $request, San $san): RedirectResponse
    {
        Gate::authorize('update', $san);

        $moc = ThoiGian::cacMoc();
        $d = $request->validate([
            'loai_ngays' => ['required', 'array', 'min:1'],
            'loai_ngays.*' => [Rule::in(array_keys(KhungGio::LOAI_NGAY))],
            'thoi_luong' => ['required', Rule::in([60, 90, 120])],
            'gia' => ['required', 'integer', 'min:0', 'max:100000000'],
            'gio_vang_tu' => ['nullable', Rule::in($moc)],
            'gio_vang_den' => ['nullable', Rule::in($moc)],
            'gia_vang' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'ghi_de' => ['nullable', 'boolean'],
        ], [
            'loai_ngays.required' => 'Chọn ít nhất một loại ngày.',
        ]);

        $coGioVang = ! empty($d['gio_vang_tu']) && ! empty($d['gio_vang_den']) && ! empty($d['gia_vang']);

        $so = $this->bangGia->taoNhanh(
            $san, $d['loai_ngays'], (int) $d['thoi_luong'], (int) $d['gia'],
            $coGioVang ? ThoiGian::phut($d['gio_vang_tu']) : null,
            $coGioVang ? ThoiGian::phut($d['gio_vang_den']) : null,
            $coGioVang ? (int) $d['gia_vang'] : null,
            (bool) ($d['ghi_de'] ?? false),
        );

        return back()->with('thanh_cong', $so > 0 ? "Đã tạo {$so} khung giờ." : 'Không tạo thêm khung nào (đã có đủ hoặc bị trùng giờ).');
    }

    /** @return array dữ liệu sạch để lưu */
    private function kiemTra(Request $request, San $san, ?KhungGio $khung = null): array
    {
        $d = $request->validate([
            'loai_ngay' => ['required', Rule::in(array_keys(KhungGio::LOAI_NGAY))],
            'gio_bat_dau' => ['required', 'string'],
            'gio_ket_thuc' => ['required', 'string'],
            'gia' => ['required', 'integer', 'min:0', 'max:100000000'],
            'la_gio_vang' => ['nullable', 'boolean'],
            'dang_ap_dung' => ['nullable', 'boolean'],
        ]);

        foreach (['gio_bat_dau', 'gio_ket_thuc'] as $cot) {
            if (! ThoiGian::hopLe($d[$cot])) {
                throw ValidationException::withMessages([$cot => 'Giờ phải theo bước 15 phút (VD 17:00, 17:15, 17:30).']);
            }
        }

        $bd = ThoiGian::phut($d['gio_bat_dau']);
        $kt = ThoiGian::phut($d['gio_ket_thuc']);

        if ($kt <= $bd) {
            throw ValidationException::withMessages(['gio_ket_thuc' => 'Giờ kết thúc phải sau giờ bắt đầu.']);
        }
        if ($bd < ThoiGian::phut($san->gio_mo_cua) || $kt > ThoiGian::phut($san->gio_dong_cua)) {
            throw ValidationException::withMessages(['gio_bat_dau' => 'Khung giờ phải nằm trong giờ mở cửa '
                .ThoiGian::chuoi(ThoiGian::phut($san->gio_mo_cua)).'–'.ThoiGian::chuoi(ThoiGian::phut($san->gio_dong_cua)).'.']);
        }
        if ($this->bangGia->biChong($san, $d['loai_ngay'], $bd, $kt, $khung?->id)) {
            throw ValidationException::withMessages(['gio_bat_dau' => 'Khung giờ này chồng lên một khung đã có.']);
        }

        return [
            'loai_ngay' => $d['loai_ngay'],
            'gio_bat_dau' => ThoiGian::csdl($bd),
            'gio_ket_thuc' => ThoiGian::csdl($kt),
            'gia' => (int) $d['gia'],
            'la_gio_vang' => $request->boolean('la_gio_vang'),
            'dang_ap_dung' => $request->has('dang_ap_dung') ? $request->boolean('dang_ap_dung') : true,
        ];
    }
}
