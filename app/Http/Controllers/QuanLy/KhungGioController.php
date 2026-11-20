<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\KhungGio;
use App\Models\San;
use App\Services\BangGia;
use App\Services\BangGiaChung;
use App\Support\ThoiGian;
use App\Support\Tien;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KhungGioController extends Controller
{
    public function __construct(private BangGia $bangGia, private BangGiaChung $giaChung)
    {
    }

    private function dongBo(San $san, string $thongBao): string
    {
        $so = $this->giaChung->dongBo($san);

        return $so ? $thongBao.' Đã áp dụng cho '.$so.' sân '.$san->loai_san.' khác cùng cơ sở.' : $thongBao;
    }

    public function index(San $san): View
    {
        Gate::authorize('view', $san);

        return view('quan-ly.san.khung-gio', [
            'san' => $san,
            'theoLoai' => $san->khungGios()->get()->groupBy('loai_ngay'),
            'cacMoc' => ThoiGian::cacMoc(ThoiGian::phut($san->gio_mo_cua), ThoiGian::phut($san->gio_dong_cua)),
            'tyLe' => (int) ($san->chuSan->hoSoChuSan?->ty_le_coc ?? 30),
            'sanNhanGia' => $this->giaChung->sanNhan($san),
        ]);
    }

    public function store(Request $request, San $san): RedirectResponse
    {
        Gate::authorize('update', $san);

        $d = $this->kiemTra($request, $san);

        $san->khungGios()->create($d);

        return back()->with('thanh_cong', $this->dongBo($san, 'Đã thêm khung giờ.'))->withFragment($d['loai_ngay']);
    }

    public function update(Request $request, KhungGio $khungGio): RedirectResponse
    {
        $san = $khungGio->san;
        Gate::authorize('update', $san);

        $d = $this->kiemTra($request, $san, $khungGio);
        $khungGio->update($d);

        return back()->with('thanh_cong', $this->dongBo($san, 'Đã cập nhật khung '.$khungGio->nhan().'.'))->withFragment($d['loai_ngay']);
    }

    public function destroy(KhungGio $khungGio): RedirectResponse
    {
        $san = $khungGio->san;
        Gate::authorize('update', $san);

        $khungGio->delete();

        return back()->with('thanh_cong', $this->dongBo($san, 'Đã xoá khung giờ.'));
    }

    public function taoNhanh(Request $request, San $san): RedirectResponse
    {
        Gate::authorize('update', $san);

        $this->chuanHoaTien($request, ['gia', 'gia_vang']);
        $khacNgay = $request->boolean('gia_khac_ngay');
        $muc = collect($request->input('muc', []))
            ->map(fn ($m) => ['tu' => ThoiGian::chuanHoa($m['tu'] ?? null), 'den' => ThoiGian::chuanHoa($m['den'] ?? null),
                'gia' => Tien::so($m['gia'] ?? null), 'vang' => ! empty($m['vang']),
                'cuoi_tuan' => $khacNgay ? Tien::so($m['gia_cuoi_tuan'] ?? null) : null,
                'ngay_le' => $khacNgay ? Tien::so($m['gia_le'] ?? null) : null])
            ->filter(fn ($m) => $m['tu'] && $m['den'] && $m['gia'] !== null)
            ->values();

        $d = $request->validate([
            'loai_ngays' => ['required', 'array', 'min:1'],
            'loai_ngays.*' => [Rule::in(array_keys(KhungGio::LOAI_NGAY))],
            'thoi_luong' => ['required', 'integer', 'min:30', 'max:300'],
            'gia' => [$muc->isEmpty() ? 'required' : 'nullable', 'integer', 'min:0', 'max:100000000'],
            'gio_vang_tu' => ['nullable', 'string'],
            'gio_vang_den' => ['nullable', 'string'],
            'gia_vang' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'ghi_de' => ['nullable', 'boolean'],
        ], [
            'loai_ngays.required' => 'Chọn ít nhất một loại ngày.',
            'gia.required' => 'Nhập giá cho ít nhất một buổi.',
        ]);
        if ($muc->contains(fn ($m) => max($m['gia'], $m['cuoi_tuan'] ?? 0, $m['ngay_le'] ?? 0) > 100000000)) {
            throw ValidationException::withMessages(['gia' => 'Giá mỗi khung tối đa 100.000.000đ — kiểm tra lại số vừa nhập.']);
        }
        if ($d['thoi_luong'] % ThoiGian::BUOC_PHUT !== 0) {
            throw ValidationException::withMessages(['thoi_luong' => 'Thời lượng trận phải là bội số 15 phút (VD 60, 75, 90).']);
        }

        // Kiểu cũ (1 giá + giờ vàng) quy về danh sách mức giá
        if ($muc->isEmpty()) {
            $muc = collect([['tu' => '00:00', 'den' => '24:00', 'gia' => (int) $d['gia'], 'vang' => false]]);
            $tu = ThoiGian::chuanHoa($d['gio_vang_tu'] ?? null);
            $den = ThoiGian::chuanHoa($d['gio_vang_den'] ?? null);
            if ($tu && $den && ! empty($d['gia_vang'])) {
                $muc->prepend(['tu' => $tu, 'den' => $den, 'gia' => (int) $d['gia_vang'], 'vang' => true]);
            }
        }

        $so = 0;
        foreach ($d['loai_ngays'] as $loai) {
            $mucLoai = $muc->map(fn ($m) => ['gia' => (int) ($m[$loai] ?? null ?: $m['gia'])] + $m)
                ->map(fn ($m) => array_intersect_key($m, array_flip(['tu', 'den', 'gia', 'vang'])))->all();
            $so += $this->bangGia->taoNhanhTheoMuc($san, [$loai], (int) $d['thoi_luong'], $mucLoai, (bool) ($d['ghi_de'] ?? false));
        }

        return back()->with('thanh_cong', $this->dongBo($san, $so > 0 ? "Đã tạo {$so} khung giờ." : 'Không tạo thêm khung nào (đã có đủ hoặc bị trùng giờ).'));
    }

    /** Lưu cùng lúc mọi khung của 1 loại ngày (sửa giá, cọc, giờ hàng loạt). */
    public function luuTatCa(Request $request, San $san): RedirectResponse
    {
        Gate::authorize('update', $san);

        $loai = $request->validate(['loai_ngay' => ['required', Rule::in(array_keys(KhungGio::LOAI_NGAY))]])['loai_ngay'];
        $hienCo = $san->khungGios()->where('loai_ngay', $loai)->get()->keyBy('id');
        $moi = [];
        foreach ((array) $request->input('khung', []) as $id => $k) {
            $kg = $hienCo->get((int) $id);
            if (! $kg) {
                continue;
            }
            $moi[$kg->id] = $this->sach($san, $k, "khung.$id.", $kg->nhan());
        }

        // Kiểm tra chồng giờ trên toàn bộ danh sách sau khi sửa
        $tatCa = $hienCo->map(fn ($kg) => $moi[$kg->id] ?? ['bd' => $kg->phutBatDau(), 'kt' => $kg->phutKetThuc(), 'nhan' => $kg->nhan()])->values();
        foreach ($tatCa as $i => $a) {
            foreach ($tatCa as $j => $b) {
                if ($i < $j && ThoiGian::chong($a['bd'], $a['kt'], $b['bd'], $b['kt'])) {
                    throw ValidationException::withMessages(['khung' => 'Khung '.ThoiGian::chuoi($a['bd']).'–'.ThoiGian::chuoi($a['kt'])
                        .' chồng lên khung '.ThoiGian::chuoi($b['bd']).'–'.ThoiGian::chuoi($b['kt']).'.']);
                }
            }
        }

        DB::transaction(function () use ($moi, $hienCo) {
            foreach ($moi as $id => $m) {
                $hienCo[$id]->update($m['luu']);
            }
        });

        return back()->with('thanh_cong', $this->dongBo($san, 'Đã lưu '.count($moi).' khung giờ.'))->withFragment($loai);
    }

    /** Kiểm tra 1 dòng khung trong form lưu hàng loạt. */
    private function sach(San $san, array $k, string $tienTo, string $nhan): array
    {
        $bd = ThoiGian::chuanHoa($k['gio_bat_dau'] ?? null);
        $kt = ThoiGian::chuanHoa($k['gio_ket_thuc'] ?? null);
        $gia = Tien::so($k['gia'] ?? null);
        $coc = Tien::so($k['tien_coc'] ?? null);
        $loi = fn ($s) => throw ValidationException::withMessages([$tienTo.'gia' => "Khung $nhan: $s"]);

        if (! $bd || ! $kt || ! ThoiGian::hopLe($bd) || ! ThoiGian::hopLe($kt)) {
            $loi('giờ phải theo bước 15 phút (VD 17:00, 17:15, 17:30).');
        }
        [$pbd, $pkt] = [ThoiGian::phut($bd), ThoiGian::phut($kt)];
        if ($pkt <= $pbd) {
            $loi('giờ kết thúc phải sau giờ bắt đầu.');
        }
        if ($pbd < ThoiGian::phut($san->gio_mo_cua) || $pkt > ThoiGian::phut($san->gio_dong_cua)) {
            $loi('phải nằm trong giờ mở cửa.');
        }
        if ($gia === null || $gia > 100000000) {
            $loi('giá không hợp lệ.');
        }
        if ($coc !== null && $coc > $gia) {
            $loi('tiền cọc không được lớn hơn giá.');
        }

        return ['bd' => $pbd, 'kt' => $pkt, 'luu' => [
            'gio_bat_dau' => ThoiGian::csdl($pbd), 'gio_ket_thuc' => ThoiGian::csdl($pkt), 'gia' => $gia, 'tien_coc' => $coc,
            'la_gio_vang' => ! empty($k['la_gio_vang']), 'dang_ap_dung' => ! empty($k['dang_ap_dung']),
        ]];
    }

    private function chuanHoaTien(Request $request, array $cot): void
    {
        foreach ($cot as $c) {
            if ($request->has($c)) {
                $request->merge([$c => Tien::so($request->input($c))]);
            }
        }
    }

    /** @return array dữ liệu sạch để lưu */
    private function kiemTra(Request $request, San $san, ?KhungGio $khung = null): array
    {
        $this->chuanHoaTien($request, ['gia', 'tien_coc']);
        foreach (['gio_bat_dau', 'gio_ket_thuc'] as $cot) {
            if ($request->filled($cot)) {
                $request->merge([$cot => ThoiGian::chuanHoa($request->input($cot)) ?? $request->input($cot)]);
            }
        }
        $d = $request->validate([
            'loai_ngay' => ['required', Rule::in(array_keys(KhungGio::LOAI_NGAY))],
            'gio_bat_dau' => ['required', 'string'],
            'gio_ket_thuc' => ['required', 'string'],
            'gia' => ['required', 'integer', 'min:0', 'max:100000000'],
            'tien_coc' => ['nullable', 'integer', 'min:0', 'lte:gia'],
            'la_gio_vang' => ['nullable', 'boolean'],
            'dang_ap_dung' => ['nullable', 'boolean'],
        ], ['tien_coc.lte' => 'Tiền cọc không được lớn hơn giá.']);

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
            'tien_coc' => $d['tien_coc'] ?? null,
            'la_gio_vang' => $request->boolean('la_gio_vang'),
            'dang_ap_dung' => $request->has('dang_ap_dung') ? $request->boolean('dang_ap_dung') : true,
        ];
    }
}
