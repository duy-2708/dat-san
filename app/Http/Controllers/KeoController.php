<?php

namespace App\Http\Controllers;

use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\Keo;
use App\Models\ThamGiaKeo;
use App\Models\Doi;
use App\Models\San;
use App\Models\User;
use App\Models\XinKeo;
use App\Services\BangGia;
use App\Services\KeoService;
use Carbon\Carbon;
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
        $keos = Keo::with(['datSan.san.xaPhuong.tinhThanh', 'san.xaPhuong.tinhThanh', 'khungGio', 'nguoiTao', 'daiDienA', 'doiA'])
            ->where('trang_thai', 'dang_tim')->where('cong_khai', true)
            ->when(in_array($loai, ['doi_dau', 'ghep_le'], true), fn ($q) => $q->where('loai', $loai))
            ->where(fn ($q) => $q->whereHas('datSan', fn ($q) => $q->where('ngay', '>=', today()->toDateString()))
                ->orWhere('ngay', '>=', today()->toDateString()))
            ->get()
            ->filter(fn (Keo $k) => $k->batDauLuc()->isFuture())
            ->sortBy(fn (Keo $k) => $k->batDauLuc())
            ->values();

        return view('keo.index', compact('keos', 'loai'));
    }

    public function show(Request $request, Keo $keo): View
    {
        $keo->load(['datSan.san.chuSan.hoSoChuSan', 'datSan.san.xaPhuong.tinhThanh', 'san.chuSan.hoSoChuSan', 'san.xaPhuong.tinhThanh',
            'khungGio', 'thamGias.user', 'nguoiTao', 'daiDienB', 'doiA', 'doiB', 'xinKeos.user', 'xinKeos.doi']);
        $u = $request->user();

        // Kèo riêng: chỉ ai có link (mã mời), người trong kèo / đã xin, hoặc phía sân mới xem được
        if (! $keo->cong_khai) {
            $duocXem = $request->query('ma') === $keo->ma_moi
                || ($u && ($keo->laDaiDien($u) || $keo->benCua($u) || $keo->xinKeos->contains('user_id', $u->id)
                    || $u->idChuSanQuanLy() === (int) $keo->sanThuc()->chu_san_id));
            abort_unless($duocXem, 404);
        }

        return view('keo.show', [
            'keo' => $keo,
            'quyetToan' => $this->keo->quyetToan($keo),
            'doiCuaToi' => $u?->laKhach() ? $u->doiDangChoi()->get() : collect(),
            'tatCaDoi' => $u && $u->idChuSanQuanLy() === (int) $keo->sanThuc()->chu_san_id ? Doi::orderBy('ten')->get(['id', 'ten', 'slug']) : collect(),
        ]);
    }

    /** Form tạo kèo TRƯỚC khi đặt sân (từ lịch sân: chọn khung trống → "Cáp kèo giờ này"). */
    public function taoTruoc(Request $request, BangGia $bangGia): View
    {
        $san = San::with('chuSan.hoSoChuSan')->findOrFail($request->integer('san'));
        $ngay = Carbon::parse((string) $request->query('ngay'))->startOfDay();
        $khung = $bangGia->khungGioTrongNgay($san, $ngay)->firstWhere('id', $request->integer('khung'));
        abort_unless($khung, 404);

        return view('keo.tao-truoc', [
            'san' => $san, 'ngay' => $ngay, 'khung' => $khung,
            'thoiLuongs' => $khung->cacThoiLuong(\App\Support\ThoiGian::phut($san->gio_dong_cua)),
            'doiCuaToi' => $request->user()->laKhach() ? $request->user()->doiDangChoi()->get() : collect(),
            'laSan' => $request->user()->idChuSanQuanLy() === (int) $san->chu_san_id,
        ]);
    }

    public function luuTruoc(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'san_id' => ['required', 'integer', 'exists:san,id'],
            'ngay' => ['required', 'date_format:Y-m-d'],
            'khung_gio_id' => ['required', 'integer'],
            'thoi_luong' => ['nullable', 'integer'],
            'doi_id' => ['nullable', 'integer'],
            'ty_le_thua' => ['required', Rule::in(array_keys(Keo::TY_LE))],
            'trinh_do' => ['required', Rule::in(array_keys(Keo::TRINH_DO))],
            'cong_khai' => ['nullable', 'boolean'],
            'mo_ta' => ['nullable', 'string', 'max:500'],
        ]);
        $k = $this->keo->taoKeoTruoc($request->user(), San::findOrFail($d['san_id']), $d['ngay'], (int) $d['khung_gio_id'],
            isset($d['thoi_luong']) ? (int) $d['thoi_luong'] : null, $d + ['cong_khai' => $request->boolean('cong_khai')]);

        return redirect()->route('keo.show', ['keo' => $k, 'ma' => $k->cong_khai ? null : $k->ma_moi])
            ->with('thanh_cong', 'Đã đăng kèo. Sân CHƯA bị giữ — khi duyệt đủ hai đội, web tự đặt sân và mỗi đội cọc 15%.');
    }

    public function xin(Request $request, Keo $keo): RedirectResponse
    {
        $d = $request->validate(['doi_id' => ['nullable', 'integer'], 'loi_nhan' => ['nullable', 'string', 'max:255']]);
        $this->keo->xinNhan($request->user(), $keo, isset($d['doi_id']) ? (int) $d['doi_id'] : null, $d['loi_nhan'] ?? null);

        return back()->with('thanh_cong', 'Đã gửi yêu cầu nhận kèo. Chờ chủ kèo / sân duyệt.');
    }

    public function duyetXin(Request $request, XinKeo $xinKeo): RedirectResponse
    {
        $d = $request->validate(['dong_y' => ['required', 'boolean']]);
        $k = $this->keo->duyetXin($request->user(), $xinKeo, (bool) $d['dong_y']);

        return back()->with('thanh_cong', match (true) {
            ! $d['dong_y'] => 'Đã từ chối.',
            $k->trang_thai === 'cho_coc' => 'Đã chốt kèo! Web đã giữ sân, mỗi đội cọc '.number_format($k->coc_moi_ben, 0, ',', '.').'đ trước '.$k->han_coc_luc->format('H:i').'.',
            default => 'Đã nhận đội vào kèo.',
        });
    }

    public function themDoi(Request $request, Keo $keo): RedirectResponse
    {
        $d = $request->validate(['doi' => ['required', 'string', 'max:150']]);
        $doi = Doi::where('ten', trim($d['doi']))->orWhere('slug', trim($d['doi']))->first();
        if (! $doi) {
            return back()->withErrors(['keo' => 'Không tìm thấy đội "'.$d['doi'].'". Chọn đúng tên trong gợi ý.']);
        }
        $this->keo->themDoiVaoKeo($request->user(), $keo, $doi);

        return back()->with('thanh_cong', 'Đã thêm '.$doi->ten.' vào kèo.');
    }

    public function create(Request $request): View
    {
        $don = DatSan::with('san')->where('ma_don', $request->query('don'))->where('khach_id', $request->user()->id)->firstOrFail();

        return view('keo.tao', ['don' => $don, 'cocB' => (int) (ceil($don->gia_chot * KeoService::TY_LE_COC_BEN_B / 100 / 1000) * 1000),
            'doiCuaToi' => $request->user()->doiDangChoi()->get()]);
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
            'doi_id' => ['nullable', 'integer'],
            'cong_khai' => ['nullable', 'boolean'],
        ]);
        $don = DatSan::where('ma_don', $d['ma_don'])->firstOrFail();

        $k = $this->keo->taoKeo($request->user(), $don, $d + ['cong_khai' => ! $request->has('cong_khai') || $request->boolean('cong_khai')]);

        return redirect()->route('keo.show', ['keo' => $k, 'ma' => $k->cong_khai ? null : $k->ma_moi])->with('thanh_cong', 'Đã đăng kèo. Chia sẻ link này cho mọi người!');
    }

    public function nhan(Request $request, Keo $keo): RedirectResponse
    {
        $gd = $this->keo->nhanKeo($request->user(), $keo, $request->integer('doi_id') ?: null);

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
        $hoSo = $tg->keo->sanThuc()->chuSan->hoSoChuSan;
        $qr = null;
        if ($giaoDich->trang_thai === 'cho_thanh_toan') {
            $qr = $giaoDich->phuong_thuc === 'payos' && ! empty($giaoDich->du_lieu_cong['qr'])
                ? VietQr::svg($giaoDich->du_lieu_cong['qr'])
                : VietQr::svg(VietQr::chuoi($hoSo->ngan_hang_bin, $hoSo->so_tai_khoan, $giaoDich->so_tien, $giaoDich->noi_dung_ck));
        }

        return view('keo.coc', ['gd' => $giaoDich, 'tg' => $tg, 'hoSo' => $hoSo, 'qr' => $qr]);
    }
}
