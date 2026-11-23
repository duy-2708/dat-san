<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\Keo;
use App\Models\ThamGiaKeo;
use App\Models\User;
use App\Models\YeuCauGoPhat;
use App\Services\KeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Phía sân với kèo: xác nhận cọc kèo, chốt kết quả, miễn phạt, gỡ hạn chế. */
class KeoController extends Controller
{
    public function __construct(private KeoService $keo)
    {
    }

    public function xacNhanCoc(Request $request, GiaoDich $giaoDich): RedirectResponse
    {
        abort_unless((int) $giaoDich->chu_san_id === $request->user()->idChuSanQuanLy(), 403);
        $this->keo->xacNhanCocKeo($giaoDich, $request->user());

        return back()->with('thanh_cong', "Đã xác nhận cọc {$giaoDich->noi_dung_ck}.");
    }

    public function chot(Request $request, Keo $keo): RedirectResponse
    {
        $this->kiemTraSan($request->user(), $keo);
        $d = $request->validate(['ket_qua' => ['required', Rule::in(array_keys(Keo::KET_QUA))]]);
        $this->keo->chotKetQua($request->user(), $keo, $d['ket_qua']);

        return back()->with('thanh_cong', 'Đã chốt kết quả: '.$keo->nhanKetQua($d['ket_qua']).'.');
    }

    public function ghiThuTien(Request $request, Keo $keo): RedirectResponse
    {
        $this->kiemTraSan($request->user(), $keo);
        $d = $request->validate([
            'ben' => ['required', Rule::in(['a', 'b'])],
            'loai' => ['required', Rule::in(['thu', 'tra_lai'])],
            'so_tien' => ['required', 'integer', 'min:1'],
            'phuong_thuc' => ['required', Rule::in(['tien_mat', 'online'])],
        ]);
        $t = $this->keo->ghiThuTien($request->user(), $keo, $d['ben'], $d['loai'], (int) $d['so_tien'], $d['phuong_thuc']);
        $nhan = $d['loai'] === 'thu' ? 'thu' : 'trả lại';

        return back()->with('thanh_cong', 'Đã ghi nhận '.$nhan.' '.number_format($t->so_tien, 0, ',', '.').'đ ('.($d['phuong_thuc'] === 'tien_mat' ? 'tiền mặt' : 'online').').');
    }

    public function mienPhat(Request $request, ThamGiaKeo $thamGia): RedirectResponse
    {
        $u = $request->user();
        abort_unless($u->laChuSan() && (int) $thamGia->keo->sanThuc()->chu_san_id === $u->id, 403);
        if ($thamGia->diem_danh !== 'vang' || $thamGia->mien_phat) {
            return back()->withErrors(['keo' => 'Người này không bị tính vắng.']);
        }
        $this->guiDeNghi($request, 'mien_phat', $thamGia->user_id, $thamGia->id);

        return back()->with('thanh_cong', "Đã gửi đề nghị miễn phạt cho {$thamGia->user->name}. Admin duyệt xong mới cộng lại uy tín.");
    }

    public function goHanChe(Request $request, User $user): RedirectResponse
    {
        $chu = $request->user();
        $lienQuan = DB::table('lich_su_uy_tin')
            ->join('keo', 'keo.id', '=', 'lich_su_uy_tin.keo_id')
            ->leftJoin('dat_san', 'dat_san.id', '=', 'keo.dat_san_id')
            ->join('san', 'san.id', '=', DB::raw('COALESCE(dat_san.san_id, keo.san_id)'))
            ->where('lich_su_uy_tin.user_id', $user->id)
            ->where('san.chu_san_id', $chu->id)
            ->exists();
        abort_unless($chu->laChuSan() && $lienQuan, 403);
        $this->guiDeNghi($request, 'go_han_che', $user->id, null);

        return back()->with('thanh_cong', "Đã gửi đề nghị gỡ hạn chế cho {$user->name}. Chờ admin duyệt.");
    }

    private function guiDeNghi(Request $request, string $loai, int $userId, ?int $thamGiaId): void
    {
        $request->validate(['ly_do' => ['nullable', 'string', 'max:300']]);
        $yc = YeuCauGoPhat::where(['loai' => $loai, 'user_id' => $userId, 'tham_gia_keo_id' => $thamGiaId, 'trang_thai' => 'cho_duyet'])->first()
            ?? new YeuCauGoPhat;
        $yc->forceFill(['loai' => $loai, 'user_id' => $userId, 'tham_gia_keo_id' => $thamGiaId, 'trang_thai' => 'cho_duyet',
            'chu_san_id' => $request->user()->id, 'ly_do' => $request->input('ly_do')])->save();
        \App\Support\BaoTin::gui(\App\Support\BaoTin::admins(), 'Đề nghị gỡ phạt mới', ($request->user()->hoSoChuSan?->ten_co_so ?? $request->user()->name)
            .' đề nghị '.mb_strtolower(YeuCauGoPhat::LOAI[$loai]).' cho '.User::find($userId)?->name.'.', route('admin.duyet-go-phat'), 'shield-exclamation');
    }

    /** Tạo kèo hộ cho lịch đặt tại chỗ (khách gọi điện, không có tài khoản). */
    public function create(Request $request, DatSan $datSan): View
    {
        abort_unless((int) $datSan->san->chu_san_id === $request->user()->idChuSanQuanLy(), 403);

        return view('keo.tao', [
            'don' => $datSan->load('san'),
            'cocB' => (int) (ceil($datSan->gia_chot * KeoService::TY_LE_COC_BEN_B / 100 / 1000) * 1000),
            'action' => route('quan-ly.keo.store', $datSan),
        ]);
    }

    public function store(Request $request, DatSan $datSan): RedirectResponse
    {
        abort_unless((int) $datSan->san->chu_san_id === $request->user()->idChuSanQuanLy(), 403);
        $d = $request->validate([
            'loai' => ['required', Rule::in(['doi_dau', 'ghep_le'])],
            'ty_le_thua' => ['required_if:loai,doi_dau', 'nullable', Rule::in(array_keys(Keo::TY_LE))],
            'so_nguoi_can' => ['required_if:loai,ghep_le', 'nullable', 'integer', 'min:1', 'max:20'],
            'tien_moi_nguoi' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'tien_coc_ca_nhan' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'trinh_do' => ['required', Rule::in(array_keys(Keo::TRINH_DO))],
            'mo_ta' => ['nullable', 'string', 'max:500'],
        ]);
        $k = $this->keo->taoKeo($request->user(), $datSan, $d);

        return redirect()->route('keo.show', $k)->with('thanh_cong', 'Đã đăng kèo hộ khách. Bạn thay mặt bên A báo kết quả sau trận.');
    }

    /** Chủ sân huỷ kèo (VD sân hỏng đèn). Ai đã cọc kèo → chờ hoàn. */
    public function huy(Request $request, Keo $keo): RedirectResponse
    {
        $u = $request->user();
        abort_unless($u->laChuSan() && (int) $keo->sanThuc()->chu_san_id === $u->id, 403);
        $request->validate(['ly_do' => ['nullable', 'string', 'max:200']]);
        $so = $this->keo->huyKeoBoiSan($keo, $request->input('ly_do') ?: 'Chủ sân huỷ');

        return back()->with('thanh_cong', 'Đã huỷ kèo.'.($so ? " Có {$so} khoản cọc cần hoàn — xem ở trang Đơn đặt." : ''));
    }

    public function khongThayTienCoc(Request $request, GiaoDich $giaoDich): RedirectResponse
    {
        abort_unless((int) $giaoDich->chu_san_id === $request->user()->idChuSanQuanLy(), 403);
        $this->keo->khongThayTienCoc($giaoDich, $request->user());

        return back()->with('thanh_cong', "Đã huỷ khoản cọc {$giaoDich->noi_dung_ck} và trừ uy tín người báo sai.");
    }

    public function daHoanCoc(Request $request, GiaoDich $giaoDich): RedirectResponse
    {
        abort_unless($request->user()->laChuSan() && (int) $giaoDich->chu_san_id === $request->user()->id, 403);
        $this->keo->xacNhanDaHoanCoc($giaoDich);

        return back()->with('thanh_cong', "Đã ghi nhận hoàn {$giaoDich->noi_dung_ck}.");
    }

    private function kiemTraSan(User $u, Keo $k): void
    {
        abort_unless($u->idChuSanQuanLy() !== null && (int) $k->sanThuc()->chu_san_id === $u->idChuSanQuanLy(), 403);
    }
}
