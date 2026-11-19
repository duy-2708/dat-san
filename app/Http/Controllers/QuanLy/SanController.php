<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\San;
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
            ->with(['anhSans', 'xaPhuong.tinhThanh', 'sanCons'])
            ->withCount('khungGios')
            ->orderBy('loai_san')->orderBy('ten')
            ->get();

        return view('quan-ly.san.index', compact('sans'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', San::class);

        if ($loi = $this->vuotGioiHanGoi($request)) {
            return redirect()->route('quan-ly.san.index')->withErrors(['goi' => $loi]);
        }

        return view('quan-ly.san.form', $this->duLieuForm($request, new San([
            'gio_mo_cua' => '06:00:00', 'gio_dong_cua' => '23:00:00', 'loai_san' => 5,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', San::class);

        if ($loi = $this->vuotGioiHanGoi($request)) {
            return redirect()->route('quan-ly.san.index')->withErrors(['goi' => $loi]);
        }

        $duLieu = $this->kiemTra($request);

        $san = DB::transaction(function () use ($request, $duLieu) {
            $san = new San($duLieu);
            $san->chu_san_id = $request->user()->id;   // luôn là người đang đăng nhập
            $san->slug = Str::slug($duLieu['ten']).'-'.Str::lower(Str::random(6));
            $san->save();
            $san->sanCons()->sync($this->sanConHopLe($request, $san));

            return $san;
        });

        return redirect()->route('quan-ly.san.khung-gio.index', $san)
            ->with('thanh_cong', "Đã tạo {$san->ten}. Bước tiếp theo: đặt khung giờ và giá.");
    }

    public function edit(Request $request, San $san): View
    {
        Gate::authorize('update', $san);

        return view('quan-ly.san.form', $this->duLieuForm($request, $san->load('xaPhuong', 'sanCons', 'anhSans')));
    }

    public function update(Request $request, San $san): RedirectResponse
    {
        Gate::authorize('update', $san);

        $duLieu = $this->kiemTra($request, $san);

        if ($san->sanChas()->where('loai_san', '<=', $duLieu['loai_san'])->exists()) {
            throw ValidationException::withMessages(['loai_san' => 'Sân này đang là một phần của sân lớn hơn, không đổi thành loại bằng/lớn hơn sân đó được.']);
        }

        DB::transaction(function () use ($request, $san, $duLieu) {
            $san->fill($duLieu)->save();
            $san->sanCons()->sync($this->sanConHopLe($request, $san));
        });

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

    private function vuotGioiHanGoi(Request $request): ?string
    {
        $goi = $request->user()->hoSoChuSan?->goiThueBao;
        if ($goi && $request->user()->sans()->count() >= $goi->so_san_toi_da) {
            return "Gói {$goi->ten} chỉ cho tối đa {$goi->so_san_toi_da} sân. Liên hệ quản trị để nâng gói.";
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
        ];
    }
}
