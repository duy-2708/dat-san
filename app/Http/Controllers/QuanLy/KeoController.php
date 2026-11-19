<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\GiaoDich;
use App\Models\Keo;
use App\Models\ThamGiaKeo;
use App\Models\User;
use App\Services\KeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

        return back()->with('thanh_cong', 'Đã chốt kết quả: '.Keo::KET_QUA[$d['ket_qua']].'.');
    }

    public function mienPhat(Request $request, ThamGiaKeo $thamGia): RedirectResponse
    {
        $u = $request->user();
        abort_unless($u->laChuSan() && (int) $thamGia->keo->datSan->san->chu_san_id === $u->id, 403);
        $this->keo->mienPhat($thamGia, $u);

        return back()->with('thanh_cong', "Đã miễn phạt cho {$thamGia->user->name}.");
    }

    /** Gỡ hạn chế cho người bị hạn chế vì kèo ở sân của mình. */
    public function goHanChe(Request $request, User $user): RedirectResponse
    {
        $chu = $request->user();
        $lienQuan = DB::table('lich_su_uy_tin')
            ->join('keo', 'keo.id', '=', 'lich_su_uy_tin.keo_id')
            ->join('dat_san', 'dat_san.id', '=', 'keo.dat_san_id')
            ->join('san', 'san.id', '=', 'dat_san.san_id')
            ->where('lich_su_uy_tin.user_id', $user->id)
            ->where('lich_su_uy_tin.ly_do', 'khong_xac_nhan_ket_qua')
            ->where('san.chu_san_id', $chu->id)
            ->exists();
        abort_unless($chu->laChuSan() && $lienQuan, 403);

        $this->keo->goHanChe($user);

        return back()->with('thanh_cong', "Đã gỡ hạn chế cho {$user->name}.");
    }

    private function kiemTraSan(User $u, Keo $k): void
    {
        abort_unless($u->idChuSanQuanLy() !== null && (int) $k->datSan->san->chu_san_id === $u->idChuSanQuanLy(), 403);
    }
}
