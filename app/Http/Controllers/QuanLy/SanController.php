<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\CoSo;
use App\Models\San;
use App\Services\BangGiaChung;
use App\Models\TinhThanh;
use App\Support\ThoiGian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SanController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', San::class);

        $sans = San::query()
            ->where('chu_san_id', $request->user()->idChuSanQuanLy()) // chỉ sân của mình
            ->with(['anhSans', 'xaPhuong.tinhThanh', 'sanCons', 'coSo'])
            ->withCount('khungGios')
            ->orderBy('loai_san')->orderBy('ten')
            ->get();

        return view('quan-ly.san.index', ['sans' => $sans, 'nhomCoSo' => $sans->groupBy('co_so_id')]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', San::class);

        if ($loi = $this->vuotGioiHanGoi($request)) {
            return redirect()->route('quan-ly.san.index')->withErrors(['goi' => $loi]);
        }

        $san = new San(['gio_mo_cua' => '06:00:00', 'gio_dong_cua' => '23:00:00', 'loai_san' => 5]);
        $coSo = CoSo::where('chu_san_id', $request->user()->id)->find($request->query('co_so'));
        if ($coSo) {
            $san->forceFill($coSo->giaTriChung());
            $san->co_so_id = $coSo->id;
        }

        return view('quan-ly.san.form', $this->duLieuForm($request, $san));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', San::class);

        $soLuong = max(1, min(10, (int) $request->input('so_luong', 1)));
        if ($loi = $this->vuotGioiHanGoi($request, $soLuong)) {
            return redirect()->route('quan-ly.san.index')->withErrors(['goi' => $loi]);
        }

        $coSo = $request->filled('co_so_id')
            ? CoSo::where('chu_san_id', $request->user()->id)->findOrFail((int) $request->input('co_so_id'))
            : null;
        if ($coSo) {
            $request->merge(collect($coSo->giaTriChung())->map(fn ($v, $k) => str_starts_with($k, 'gio_') ? substr((string) $v, 0, 5) : $v)->all()
                + ['tinh_thanh_id' => $coSo->xaPhuong?->tinh_thanh_id]);
        }
        $duLieu = $this->kiemTra($request);
        $request->validate(['ten_co_so' => ['nullable', 'string', 'max:100']]);

        $sans = DB::transaction(function () use ($request, $duLieu, $coSo, $soLuong) {
            $tao = [];
            for ($i = 0; $i < $soLuong; $i++) {
                $san = new San($duLieu);
                if ($soLuong > 1) {
                    $san->ten = mb_substr($duLieu['ten'], 0, 98).chr(65 + $i);
                }
                $san->chu_san_id = $request->user()->id;
                $san->gia_rieng = $request->boolean('gia_rieng');
                $san->slug = Str::slug($san->ten).'-'.Str::lower(Str::random(6));
                $coSo ??= CoSo::taoTuSan($san, $request->input('ten_co_so'));
                $san->co_so_id = $coSo->id;
                $san->save();
                if ($soLuong === 1) {
                    $san->sanCons()->sync($this->sanConHopLe($request, $san));
                }
                app(BangGiaChung::class)->nhanTuSanCungLoai($san);
                $tao[] = $san;
            }

            return $tao;
        });
        $san = $sans[0];

        return redirect()->route('quan-ly.san.khung-gio.index', $san)
            ->with('thanh_cong', count($sans) > 1
                ? 'Đã tạo '.count($sans).' sân: '.collect($sans)->pluck('ten')->join(', ').'. Đặt giá cho '.$san->ten.' là áp dụng cho cả '.count($sans).' sân.'
                : "Đã tạo {$san->ten}. Bước tiếp theo: đặt khung giờ và giá.");
    }

    public function edit(Request $request, San $san): View
    {
        Gate::authorize('update', $san);

        return view('quan-ly.san.form', $this->duLieuForm($request, $san->load('xaPhuong', 'sanCons', 'anhSans', 'coSo.sans')));
    }

    public function update(Request $request, San $san): RedirectResponse
    {
        Gate::authorize('update', $san);

        $coSoMoi = $request->filled('chuyen_co_so_id')
            ? CoSo::where('chu_san_id', $san->chu_san_id)->where('id', '!=', $san->co_so_id)->findOrFail((int) $request->input('chuyen_co_so_id'))
            : null;
        if ($coSoMoi) {
            $request->merge(collect($coSoMoi->giaTriChung())->map(fn ($v, $k) => str_starts_with($k, 'gio_') ? substr((string) $v, 0, 5) : $v)->all());
        }
        $duLieu = $this->kiemTra($request, $san);

        if ($san->sanChas()->where('loai_san', '<=', $duLieu['loai_san'])->exists()) {
            throw ValidationException::withMessages(['loai_san' => 'Sân này đang là một phần của sân lớn hơn, không đổi thành loại bằng/lớn hơn sân đó được.']);
        }

        $request->validate(['ten_co_so' => ['nullable', 'string', 'max:100']]);
        $nhanGiaChung = ! $request->boolean('gia_rieng') && ($san->gia_rieng || $coSoMoi || (int) $san->loai_san !== (int) $duLieu['loai_san']);

        DB::transaction(function () use ($request, $san, $duLieu, $coSoMoi) {
            $coSoCu = $san->co_so_id;
            $san->fill($duLieu);
            $san->gia_rieng = $request->boolean('gia_rieng');
            if ($coSoMoi) {
                $san->co_so_id = $coSoMoi->id;
            }
            $san->save();
            $san->sanCons()->sync($this->sanConHopLe($request, $san));
            $coSo = $coSoMoi ?? $san->coSo ?? CoSo::taoTuSan($san);
            if (! $coSoMoi) {
                $coSo->forceFill(collect(CoSo::COT_CHUNG)->mapWithKeys(fn ($cot) => [$cot => $san->getAttribute($cot)])->all()
                    + ['ten' => $request->input('ten_co_so') ?: $coSo->ten])->save();
                $coSo->dongBoXuongSan();
            } elseif ($coSoCu && ! San::where('co_so_id', $coSoCu)->exists()) {
                CoSo::whereKey($coSoCu)->delete();
            }
        });
        if ($nhanGiaChung) {
            app(BangGiaChung::class)->nhanTuSanCungLoai($san->fresh());
        }

        return redirect()->route('quan-ly.san.edit', $san)->with('thanh_cong', 'Đã lưu thông tin sân.');
    }

    /** Tạm ngưng / mở lại (thay cho xoá: sân đã có đơn đặt thì không xoá được). */
    public function doiTrangThai(San $san): RedirectResponse
    {
        Gate::authorize('update', $san);

        $san->trang_thai = $san->trang_thai === 'hoat_dong' ? 'tam_ngung' : 'hoat_dong';
        $san->save();

        return back()->with('thanh_cong', $san->trang_thai === 'hoat_dong'
            ? "Đã mở lại {$san->ten}." : "Đã tạm ngưng {$san->ten} (khách sẽ không thấy sân này).");
    }

    public function destroy(San $san): RedirectResponse
    {
        Gate::authorize('delete', $san);

        if (DB::table('dat_san')->where('san_id', $san->id)->exists()) {
            return back()->withErrors(['san' => 'Sân đã có lượt đặt, không xoá được. Hãy dùng "Tạm ngưng".']);
        }

        DB::transaction(function () use ($san) {
            DB::table('san_ghep')->where('san_cha_id', $san->id)->orWhere('san_con_id', $san->id)->delete();
            $san->delete(); // ảnh, khung giờ xoá theo (CASCADE)
        });

        return redirect()->route('quan-ly.san.index')->with('thanh_cong', "Đã xoá {$san->ten}.");
    }

    // ------------------------------------------------------------------

    private function kiemTra(Request $request, ?San $san = null): array
    {
        foreach (['gio_mo_cua', 'gio_dong_cua'] as $cot) {
            if ($request->filled($cot)) {
                $request->merge([$cot => ThoiGian::chuanHoa($request->input($cot)) ?? $request->input($cot)]);
            }
        }
        $moc = ThoiGian::cacMoc();

        $duLieu = $request->validate([
            'ten' => ['required', 'string', 'max:100'],
            'biet_danh' => ['nullable', 'string', 'max:100'],
            'loai_san' => ['required', Rule::in(array_keys(San::LOAI))],
            'xa_phuong_id' => ['required', 'exists:xa_phuong,id'],
            'dia_chi_chi_tiet' => ['required', 'string', 'max:255'],
            'mo_ta' => ['nullable', 'string', 'max:3000'],
            'tien_ich' => ['nullable', 'array'],
            'tien_ich.*' => [Rule::in(array_keys(San::TIEN_ICH))],
            'gio_mo_cua' => ['required', Rule::in($moc)],
            'gio_dong_cua' => ['required', Rule::in($moc)],
            'san_con_ids' => ['nullable', 'array'],
            'san_con_ids.*' => ['integer'],
        ], [
            'gio_mo_cua.in' => 'Giờ mở cửa phải theo bước 15 phút, VD 06:00, 06:15. Mở xuyên đêm: 00:00.',
            'gio_dong_cua.in' => 'Giờ đóng cửa phải theo bước 15 phút, VD 22:30. Mở xuyên đêm: 24:00.',
        ]);

        if (ThoiGian::phut($duLieu['gio_dong_cua']) <= ThoiGian::phut($duLieu['gio_mo_cua'])) {
            throw ValidationException::withMessages(['gio_dong_cua' => 'Giờ đóng cửa phải sau giờ mở cửa.']);
        }

        $duLieu['gio_mo_cua'] = ThoiGian::csdl(ThoiGian::phut($duLieu['gio_mo_cua']));
        $duLieu['gio_dong_cua'] = ThoiGian::csdl(ThoiGian::phut($duLieu['gio_dong_cua']));
        $duLieu['tien_ich'] = array_values($duLieu['tien_ich'] ?? []);
        unset($duLieu['san_con_ids']);

        return $duLieu;
    }

    /**
     * Sân con hợp lệ: của cùng chủ sân, nhỏ hơn sân cha, không phải chính nó,
     * và không tạo vòng lặp (sân con không được là "tổ tiên" của sân cha).
     *
     * @return int[]
     */
    private function sanConHopLe(Request $request, San $san): array
    {
        $ids = array_map('intval', $request->input('san_con_ids', []));
        if (! $ids) {
            return [];
        }

        $hopLe = San::query()
            ->whereIn('id', $ids)
            ->where('chu_san_id', $san->chu_san_id)
            ->where('loai_san', '<', $san->loai_san)
            ->where('id', '!=', $san->id)
            ->pluck('id')->all();

        $toTien = San::duyetGhep([$san->id], 'san_con_id', 'san_cha_id');
        $hopLe = array_diff($hopLe, $toTien);

        if (count($hopLe) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'san_con_ids' => 'Chỉ ghép được từ sân NHỎ HƠN của chính bạn (VD sân 11 ghép từ sân 5/7).',
            ]);
        }

        return array_values($hopLe);
    }

    private function vuotGioiHanGoi(Request $request, int $them = 1): ?string
    {
        $goi = $request->user()->hoSoChuSan?->goiApDung();
        if ($goi && $request->user()->sans()->count() + $them > $goi->so_san_toi_da) {
            return "Gói {$goi->ten} chỉ cho tối đa {$goi->so_san_toi_da} sân. Vào mục Gói dịch vụ để nâng gói.";
        }

        return null;
    }

    private function duLieuForm(Request $request, San $san): array
    {
        $tinhId = old('tinh_thanh_id', $san->xaPhuong?->tinh_thanh_id);

        return [
            'san' => $san,
            'tinhThanhs' => TinhThanh::orderBy('thu_tu')->get(),
            'tinhDangChon' => $tinhId,
            'xaPhuongs' => $tinhId ? \App\Models\XaPhuong::where('tinh_thanh_id', $tinhId)->orderBy('ten')->get() : collect(),
            'sanCoTheGhep' => $request->user()->sans()
                ->when($san->exists, fn ($q) => $q->where('id', '!=', $san->id))
                ->orderBy('loai_san')->orderBy('ten')->get(),
            'cacMoc' => ThoiGian::cacMoc(),
            'coSos' => CoSo::where('chu_san_id', $request->user()->idChuSanQuanLy())->withCount('sans')->orderBy('ten')->get(),
        ];
    }
}
