<?php

namespace App\Http\Controllers;

use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\Keo;
use App\Models\ThamGiaKeo;
use App\Models\User;
use App\Services\KeoService;
use App\Support\ChuanHoa;
use App\Support\VietQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KeoController extends Controller
{
    public function __construct(private KeoService $keo)
    {
    }

    /** Danh sách kèo đang tìm người (ai cũng xem được). */
    public function index(Request $request): View
    {
        $loai = $request->query('loai');
        $keos = Keo::with(['datSan.san.xaPhuong.tinhThanh', 'nguoiTao'])
            ->where('trang_thai', 'dang_tim')
            ->when(in_array($loai, ['doi_dau', 'ghep_le'], true), fn ($q) => $q->where('loai', $loai))
            ->whereHas('datSan', fn ($q) => $q->where('ngay', '>=', today()->toDateString()))
            ->get()
            ->filter(fn (Keo $k) => $k->datSan->batDauLuc()->isFuture())
            ->sortBy(fn (Keo $k) => $k->datSan->batDauLuc())
            ->values();

        return view('keo.index', compact('keos', 'loai'));
    }

    public function show(Keo $keo): View
    {
        $keo->load(['datSan.san.chuSan.hoSoChuSan', 'datSan.san.xaPhuong.tinhThanh', 'thamGias.user', 'nguoiTao', 'daiDienB']);

        return view('keo.show', [
            'keo' => $keo,
            'quyetToan' => $this->keo->quyetToan($keo),
        ]);
    }

    public function create(Request $request): View
    {
        $don = DatSan::with('san')->where('ma_don', $request->query('don'))->where('khach_id', $request->user()->id)->firstOrFail();

        return view('keo.tao', ['don' => $don, 'cocB' => (int) (ceil($don->gia_chot * KeoService::TY_LE_COC_BEN_B / 100 / 1000) * 1000)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'ma_don' => ['required', 'string'],
            'loai' => ['required', Rule::in(['doi_dau', 'ghep_le'])],
            'ty_le_thua' => ['required_if:loai,doi_dau', 'nullable', Rule::in(array_keys(Keo::TY_LE))],
            'so_nguoi_can' => ['required_if:loai,ghep_le', 'nullable', 'integer', 'min:1', 'max:20'],
            'tien_moi_nguoi' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'tien_coc_ca_nhan' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'trinh_do' => ['required', Rule::in(array_keys(Keo::TRINH_DO))],
            'mo_ta' => ['nullable', 'string', 'max:500'],
        ]);
        $don = DatSan::where('ma_don', $d['ma_don'])->firstOrFail();

        $k = $this->keo->taoKeo($request->user(), $don, $d);

        return redirect()->route('keo.show', $k)->with('thanh_cong', 'Đã đăng kèo. Chia sẻ link này cho mọi người!');
    }

    public function nhan(Request $request, Keo $keo): RedirectResponse
    {
        $gd = $this->keo->nhanKeo($request->user(), $keo);

        return redirect()->route('keo.coc', $gd);
    }

    public function thamGia(Request $request, Keo $keo): RedirectResponse
    {
        $gd = $this->keo->thamGiaLe($request->user(), $keo);

        return $gd ? redirect()->route('keo.coc', $gd) : back()->with('thanh_cong', 'Đã vào kèo. Nhớ đến đúng giờ để giữ uy tín!');
    }

    public function themThanhVien(Request $request, Keo $keo): RedirectResponse
    {
        $d = $request->validate(['tim' => ['required', 'string', 'max:255']]);
        $sdt = ChuanHoa::soDienThoai($d['tim']);
        $tv = User::where('vai_tro', User::KHACH)
            ->where(fn ($q) => $q->where('email', mb_strtolower(trim($d['tim'])))->orWhere('so_dien_thoai', $sdt))
            ->first();
        if (! $tv) {
            return back()->withErrors(['keo' => 'Không tìm thấy khách có email/SĐT này. Bạn ấy cần đăng ký tài khoản trước.']);
        }
        $this->keo->themThanhVien($request->user(), $keo, $tv);

        return back()->with('thanh_cong', "Đã thêm {$tv->name}.");
    }

    public function baoKetQua(Request $request, Keo $keo): RedirectResponse
    {
        $d = $request->validate(['ket_qua' => ['required', Rule::in(array_keys(Keo::KET_QUA))]]);
        $k = $this->keo->baoKetQua($request->user(), $keo, $d['ket_qua']);

        return back()->with('thanh_cong', match ($k->trang_thai) {
            'hoan_tat' => 'Hai bên đã khớp kết quả. Kèo hoàn tất!',
            'tranh_chap' => 'Hai bên báo khác nhau — chủ sân sẽ chốt. Bạn vẫn sửa được nếu báo nhầm.',
            default => 'Đã ghi nhận. Chờ bên kia xác nhận (hạn '.$k->han_xac_nhan_luc->format('H:i').').',
        });
    }

    public function diemDanh(Request $request, Keo $keo): RedirectResponse
    {
        $d = $request->validate([
            'ben' => ['nullable', Rule::in(['a', 'b'])],
            'tham_gia_id' => ['nullable', 'integer'],
            'co_mat' => ['required', 'boolean'],
        ]);
        if ($keo->laDoiDau()) {
            $this->keo->diemDanhBen($keo, $d['ben'] ?? 'a', (bool) $d['co_mat'], $request->user());
        } else {
            $tg = ThamGiaKeo::where('keo_id', $keo->id)->findOrFail($d['tham_gia_id'] ?? 0);
            $this->keo->diemDanhNguoi($tg, (bool) $d['co_mat'], $request->user());
        }

        return back()->with('thanh_cong', 'Đã điểm danh.');
    }

    public function huy(Request $request, Keo $keo): RedirectResponse
    {
        $this->keo->huyKeo($request->user(), $keo);

        return redirect()->route('khach.tai-khoan')->with('thanh_cong', 'Đã huỷ kèo.');
    }

    /** Trang quét QR cọc kèo. */
    public function coc(Request $request, GiaoDich $giaoDich): View
    {
        $u = $request->user();
        abort_unless(
            (int) $giaoDich->nguoi_tra_id === $u->id || (int) $giaoDich->chu_san_id === $u->idChuSanQuanLy(),
            403
        );
        abort_unless(in_array($giaoDich->loai, ['coc_keo_doi', 'coc_keo_le'], true), 404);

        $tg = ThamGiaKeo::with('keo.datSan.san.chuSan.hoSoChuSan')->findOrFail($giaoDich->tham_gia_keo_id);
        $hoSo = $tg->keo->datSan->san->chuSan->hoSoChuSan;
        $qr = null;
        if ($giaoDich->trang_thai === 'cho_thanh_toan') {
            $qr = $giaoDich->phuong_thuc === 'payos' && ! empty($giaoDich->du_lieu_cong['qr'])
                ? VietQr::svg($giaoDich->du_lieu_cong['qr'])
                : VietQr::svg(VietQr::chuoi($hoSo->ngan_hang_bin, $hoSo->so_tai_khoan, $giaoDich->so_tien, $giaoDich->noi_dung_ck));
        }

        return view('keo.coc', ['gd' => $giaoDich, 'tg' => $tg, 'hoSo' => $hoSo, 'qr' => $qr]);
    }
}
