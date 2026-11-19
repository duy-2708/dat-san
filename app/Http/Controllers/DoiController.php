<?php

namespace App\Http\Controllers;

use App\Models\Doi;
use App\Models\ThanhVienDoi;
use App\Models\TinhThanh;
use App\Models\TranDau;
use App\Models\User;
use App\Services\DoiService;
use App\Support\ChuanHoa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DoiController extends Controller
{
    public function __construct(private DoiService $doi)
    {
    }

    /** Trang đội (công khai): logo, thành viên, thành tích giải. */
    /** Danh sách đội để tìm & xin vào. */
    public function index(Request $request): View
    {
        $tuKhoa = trim((string) $request->query('q'));
        $tinh = $request->integer('tinh') ?: null;

        return view('doi.index', [
            'dois' => Doi::with(['tinhThanh', 'doiTruong'])
                ->withCount(['thanhViens as so_thanh_vien' => fn ($q) => $q->where('trang_thai', 'dang_choi')])
                ->when($tuKhoa !== '', fn ($q) => $q->where('ten', 'like', '%'.$tuKhoa.'%'))
                ->when($tinh, fn ($q, $t) => $q->where('tinh_thanh_id', $t))
                ->latest()->paginate(24)->withQueryString(),
            'tinhThanhs' => TinhThanh::orderBy('thu_tu')->get(),
            'tuKhoa' => $tuKhoa, 'tinh' => $tinh,
        ]);
    }

    public function show(Doi $doi): View
    {
        $doi->load(['doiTruong', 'tinhThanh', 'thanhViens.user']);

        return view('doi.show', [
            'doi' => $doi,
            'thanhTich' => $doi->thamGiaGiais()->with('giaiDau')->where('trang_thai', 'da_duyet')
                ->whereHas('giaiDau', fn ($q) => $q->where('trang_thai', 'ket_thuc'))->get()->sortBy(fn ($d) => $d->thu_hang ?? 999),
            'tranGanDay' => TranDau::with(['doiNha', 'doiKhach', 'giaiDau'])
                ->where(fn ($q) => $q->where('doi_nha_id', $doi->id)->orWhere('doi_khach_id', $doi->id))
                ->where('trang_thai', 'da_xong')->latest('updated_at')->limit(10)->get(),
        ]);
    }

    public function create(): View
    {
        return view('doi.tao', ['tinhThanhs' => TinhThanh::orderBy('thu_tu')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->kiemTra($request);
        $logo = $request->file('logo')?->store('doi', 'public');
        $doi = $this->doi->taoDoi($request->user(), $d, $logo);

        return redirect()->route('doi.show', $doi)->with('thanh_cong', 'Đã tạo đội. Mời đồng đội vào bằng SĐT/email, hoặc gửi link trang đội cho họ xin vào.');
    }

    public function update(Request $request, Doi $doi): RedirectResponse
    {
        abort_unless($doi->laDoiTruong($request->user()), 403);
        $d = $this->kiemTra($request);
        if ($request->hasFile('logo')) {
            if ($doi->logo) {
                Storage::disk('public')->delete($doi->logo);
            }
            $doi->logo = $request->file('logo')->store('doi', 'public');
        }
        $doi->forceFill(['ten' => $d['ten'], 'tinh_thanh_id' => $d['tinh_thanh_id'] ?? null, 'mo_ta' => $d['mo_ta'] ?? null])->save();

        return back()->with('thanh_cong', 'Đã lưu thông tin đội.');
    }

    public function xinVao(Request $request, Doi $doi): RedirectResponse
    {
        $this->doi->xinVao($request->user(), $doi);

        return back()->with('thanh_cong', 'Đã gửi yêu cầu, chờ đội trưởng duyệt.');
    }

    public function themThanhVien(Request $request, Doi $doi): RedirectResponse
    {
        $d = $request->validate(['tim' => ['required', 'string', 'max:255'], 'so_ao' => ['nullable', 'integer', 'min:0', 'max:99']]);
        $tv = User::where('email', mb_strtolower(trim($d['tim'])))->orWhere('so_dien_thoai', ChuanHoa::soDienThoai($d['tim']))->first();
        if (! $tv) {
            return back()->withErrors(['doi' => 'Không tìm thấy tài khoản có email/SĐT này.']);
        }
        $this->doi->themThanhVien($request->user(), $doi, $tv, $d['so_ao'] ?? null);

        return back()->with('thanh_cong', "Đã thêm {$tv->name} vào đội.");
    }

    public function duyet(Request $request, ThanhVienDoi $thanhVien): RedirectResponse
    {
        $this->doi->duyet($request->user(), $thanhVien, $request->boolean('dong_y'));

        return back()->with('thanh_cong', $request->boolean('dong_y') ? 'Đã nhận vào đội.' : 'Đã từ chối.');
    }

    public function roi(Request $request, ThanhVienDoi $thanhVien): RedirectResponse
    {
        $this->doi->roiDoi($request->user(), $thanhVien);

        return back()->with('thanh_cong', 'Đã cập nhật thành viên.');
    }

    public function chuyenDoiTruong(Request $request, Doi $doi): RedirectResponse
    {
        $moi = User::findOrFail((int) $request->input('user_id'));
        $this->doi->chuyenDoiTruong($request->user(), $doi, $moi);

        return back()->with('thanh_cong', "{$moi->name} là đội trưởng mới.");
    }

    private function kiemTra(Request $request): array
    {
        return $request->validate([
            'ten' => ['required', 'string', 'max:100'],
            'tinh_thanh_id' => ['nullable', 'exists:tinh_thanh,id'],
            'mo_ta' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
    }
}
