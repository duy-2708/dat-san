<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\DoiGiaiDau;
use App\Models\GiaiDau;
use App\Models\San;
use App\Models\TranDau;
use App\Services\GiaiDauService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Chủ sân tổ chức giải. Mọi thao tác chỉ trên giải CỦA MÌNH. */
class GiaiDauController extends Controller
{
    public function __construct(private GiaiDauService $giai)
    {
    }

    public function index(Request $request): View
    {
        return view('quan-ly.giai-dau.index', [
            'giais' => GiaiDau::where('chu_san_id', $request->user()->id)->withCount('doiThamGias')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('quan-ly.giai-dau.tao');
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'ten' => ['required', 'string', 'max:150'],
            'loai_san' => ['required', Rule::in([5, 7, 11])],
            'the_thuc' => ['required', Rule::in(['vong_tron', 'loai_truc_tiep'])],
            'tu_ngay' => ['required', 'date', 'after_or_equal:today'],
            'den_ngay' => ['required', 'date', 'after_or_equal:tu_ngay'],
            'le_phi' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'so_doi_toi_da' => ['required', 'integer', 'min:2', 'max:64'],
            'mo_ta' => ['nullable', 'string', 'max:3000'],
        ]);
        $g = new GiaiDau;
        $g->forceFill($d + ['chu_san_id' => $request->user()->id, 'le_phi' => $d['le_phi'] ?? 0,
            'slug' => Str::slug($d['ten']).'-'.Str::lower(Str::random(5)), 'trang_thai' => 'mo_dang_ky'])->save();

        return redirect()->route('quan-ly.giai-dau.show', $g)->with('thanh_cong', 'Đã tạo giải, đang mở đăng ký.');
    }

    public function show(Request $request, GiaiDau $giaiDau): View
    {
        $this->cuaToi($request, $giaiDau);
        $giaiDau->load(['doiThamGias.doi', 'doiThamGias.cauThus', 'tranDaus.doiNha', 'tranDaus.doiKhach']);

        return view('quan-ly.giai-dau.show', [
            'g' => $giaiDau,
            'bxh' => $this->giai->bangXepHang($giaiDau),
            'sans' => San::where('chu_san_id', $request->user()->id)->orderBy('ten')->get(),
        ]);
    }

    public function duyet(Request $request, DoiGiaiDau $dangKy): RedirectResponse
    {
        $this->cuaToi($request, $dangKy->giaiDau);
        $this->giai->duyet($dangKy, $request->boolean('dong_y'));

        return back()->with('thanh_cong', $request->boolean('dong_y') ? 'Đã duyệt đội.' : 'Đã từ chối.');
    }

    public function dongDangKy(Request $request, GiaiDau $giaiDau): RedirectResponse
    {
        $this->cuaToi($request, $giaiDau);
        $so = $this->giai->dongDangKy($giaiDau);

        return back()->with('thanh_cong', 'Giải bắt đầu!'.($so ? " Đã tự xếp {$so} trận vòng tròn." : ' Hãy thêm các cặp đấu.'));
    }

    public function themTran(Request $request, GiaiDau $giaiDau): RedirectResponse
    {
        $this->cuaToi($request, $giaiDau);
        $d = $request->validate([
            'doi_nha_id' => ['required', 'integer'], 'doi_khach_id' => ['required', 'integer'],
            'vong' => ['required', 'string', 'max:50'], 'thoi_gian' => ['nullable', 'date'], 'san_id' => ['nullable', 'integer'],
        ]);
        $this->giai->themTran($giaiDau, (int) $d['doi_nha_id'], (int) $d['doi_khach_id'], $d['vong'], $d['thoi_gian'] ?? null, $d['san_id'] ?? null);

        return back()->with('thanh_cong', 'Đã thêm trận.');
    }

    public function ketQua(Request $request, TranDau $tranDau): RedirectResponse
    {
        $this->cuaToi($request, $tranDau->giaiDau);
        $d = $request->validate(['ban_thang_nha' => ['required', 'integer', 'min:0', 'max:99'], 'ban_thang_khach' => ['required', 'integer', 'min:0', 'max:99']]);
        $this->giai->nhapKetQua($tranDau, (int) $d['ban_thang_nha'], (int) $d['ban_thang_khach']);

        return back()->with('thanh_cong', 'Đã lưu kết quả.');
    }

    public function ketThuc(Request $request, GiaiDau $giaiDau): RedirectResponse
    {
        $this->cuaToi($request, $giaiDau);
        $d = $request->validate([
            'xep_hang' => ['nullable', 'array'],
            'xep_hang.*.thu_hang' => ['nullable', 'integer', 'min:1', 'max:64'],
            'xep_hang.*.danh_hieu' => ['nullable', 'string', 'max:100'],
        ]);
        $this->giai->ketThuc($giaiDau, $d['xep_hang'] ?? []);

        return back()->with('thanh_cong', 'Giải đã kết thúc. Thành tích đã hiện trên trang đội và hồ sơ cầu thủ.');
    }

    private function cuaToi(Request $request, GiaiDau $g): void
    {
        abort_unless((int) $g->chu_san_id === $request->user()->id, 403);
    }
}
