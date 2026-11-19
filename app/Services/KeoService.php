<?php

namespace App\Services;

use App\Models\DatSan;
use App\Models\Doi;
use App\Models\GiaoDich;
use App\Models\Keo;
use App\Models\San;
use App\Models\ThamGiaKeo;
use App\Models\User;
use App\Models\XinKeo;
use Carbon\Carbon;
use App\Payments\QuanLyCong;
use App\Support\ThoiGian;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cáp kèo. Hai loại:
 *  - doi_dau: bên A (người đặt sân) tìm bên B. Chọn trước tỉ lệ ĐỘI THUA chịu tiền sân (5/5, 6/4, 7/3, 100%), hoà = 5/5.
 *    Bên A đã cọc 30% khi đặt sân; bên B nhận kèo cọc 15% tiền sân (vào tài khoản chủ sân).
 *    Sau trận, đại diện HAI bên phải báo kết quả trong 60 phút (sửa được nếu nhầm). Khớp → hoàn tất.
 *    Lệch → tranh chấp, chủ sân chốt. Quá 60 phút bên nào chưa báo → bên đó bị HẠN CHẾ (không đặt sân/vào kèo).
 *  - ghep_le: đội thiếu người, người lẻ xin vào (có thể phải cọc cá nhân).
 * Uy tín: vắng −10, có mặt +1 (tối đa 100); vắng 3 lần trong 30 ngày → khoá vào kèo 30 ngày.
 */
class KeoService
{
    public const TY_LE_COC_BEN_B = 15;
    public const PHUT_XAC_NHAN = 60;
    public const PHAT_VANG = 10;
    public const THUONG_DI = 1;
    public const SO_VANG_KHOA = 3;
    public const NGAY_XET_VANG = 30;
    public const NGAY_KHOA_KEO = 30;
    public const PHAT_BUNG_KEO = 5;
    public const PHUT_TOI_THIEU_TRUOC_GIO = 60; // kèo trước: giờ đá phải còn ≥ 60' để kịp cọc

    public function __construct(private QuanLyCong $quanLyCong)
    {
    }

    // ================================================================ TẠO / THAM GIA

    /**
     * Khách tạo kèo cho lịch của mình; HOẶC chủ sân / nhân viên tạo kèo hộ cho lịch đặt TẠI CHỖ
     * (khách gọi điện, không có tài khoản). Kèo do sân tạo: bên A không có thành viên trên web,
     * chủ sân thay mặt bên A báo kết quả.
     */
    public function taoKeo(User $u, DatSan $don, array $d): Keo
    {
        $laSan = $this->laPhiaSan($u, $don);
        $hopLe = $don->trang_thai === 'da_dat' && $don->batDauLuc()->isFuture()
            && ($laSan ? $don->khach_id === null : (int) $don->khach_id === $u->id);
        if (! $hopLe) {
            $this->loi($laSan
                ? 'Sân chỉ tạo kèo hộ cho lịch đặt tại chỗ, chưa tới giờ đá.'
                : 'Chỉ tạo kèo cho lịch đã đặt (đã cọc) của bạn và chưa tới giờ đá.');
        }
        if (! $laSan && ($lyDo = $u->lyDoKhongVaoKeo(true))) {
            $this->loi($lyDo);
        }

        $doiA = ! $laSan && $d['loai'] === 'doi_dau' ? $this->doiCuaNguoi($u, $d['doi_id'] ?? null) : null;
        if ($doiA) {
            $this->kiemTraDoiRanh($doiA, $don->batDauLuc(), $don->ketThucLuc());
        }

        $k = new Keo;
        $k->forceFill([
            'dat_san_id' => $don->id, 'loai' => $d['loai'], 'nguoi_tao_id' => $u->id, 'doi_a_id' => $doiA?->id,
            'cong_khai' => ! isset($d['cong_khai']) || (bool) $d['cong_khai'], 'ma_moi' => Str::lower(Str::random(10)),
            'trinh_do' => $d['trinh_do'] ?? 'trung_binh', 'mo_ta' => $d['mo_ta'] ?? null,
            'tien_san' => $don->gia_chot,
            'han_xac_nhan_luc' => $don->ketThucLuc()->addMinutes(self::PHUT_XAC_NHAN),
            'trang_thai' => 'dang_tim',
        ]);
        if ($d['loai'] === 'doi_dau') {
            $k->forceFill([
                'ty_le_thua' => (int) $d['ty_le_thua'],
                'coc_moi_ben' => (int) (ceil($don->gia_chot * self::TY_LE_COC_BEN_B / 100 / 1000) * 1000),
            ]);
        } else {
            $k->forceFill([
                'so_nguoi_can' => (int) $d['so_nguoi_can'],
                'tien_moi_nguoi' => (int) ($d['tien_moi_nguoi'] ?? 0),
                'tien_coc_ca_nhan' => (int) ($d['tien_coc_ca_nhan'] ?? 0),
            ]);
        }

        try {
            DB::transaction(function () use ($k, $u, $laSan) {
                $k->save();
                if (! $laSan) {
                    $this->ghiThamGia($k, $u, 'a', 'dai_dien', 'da_xac_nhan');
                }
            });
        } catch (UniqueConstraintViolationException) {
            $this->loi('Lịch này đã có kèo.');
        }

        return $k;
    }

    /** Đội đấu đội: nhận kèo làm bên B → phải cọc 15% trong thời gian giữ chỗ. */
    public function nhanKeo(User $u, Keo $k, ?int $doiId = null): GiaoDich
    {
        if ($k->laKeoTruoc()) {
            $this->loi('Kèo này cần gửi yêu cầu nhận kèo để được duyệt.');
        }
        $this->kiemTraVao($u, $k, 'doi_dau', true);
        $doiB = $this->doiCuaNguoi($u, $doiId);
        $this->kiemTraDoiRanh($doiB, $k->batDauLuc(), $k->ketThucLuc(), $k->id);

        return DB::transaction(function () use ($u, $k, $doiB) {
            $k = Keo::lockForUpdate()->findOrFail($k->id);
            if ($k->dai_dien_b_id) {
                $this->loi('Kèo vừa có đội khác nhận.');
            }
            $phut = $this->hoSo($k)->phut_giu_cho;
            $tg = $this->ghiThamGia($k, $u, 'b', 'dai_dien', 'cho_coc', now()->addMinutes($phut));
            $k->forceFill(['dai_dien_b_id' => $u->id, 'doi_b_id' => $doiB?->id])->save();

            return $this->taoCoc($k, $tg, 'coc_keo_doi', $k->coc_moi_ben);
        });
    }

    /** Ghép lẻ: xin vào. Trả về giao dịch cọc nếu kèo có cọc, null nếu không. */
    public function thamGiaLe(User $u, Keo $k): ?GiaoDich
    {
        $coCoc = $k->tien_coc_ca_nhan > 0;
        $this->kiemTraVao($u, $k, 'ghep_le', $coCoc);

        return DB::transaction(function () use ($u, $k, $coCoc) {
            $k = Keo::lockForUpdate()->findOrFail($k->id);
            if ($this->soNguoiLe($k) >= $k->so_nguoi_can) {
                $this->loi('Kèo đã đủ người.');
            }
            $tg = $this->ghiThamGia($k, $u, 'a', 'nguoi_le', $coCoc ? 'cho_coc' : 'da_xac_nhan',
                $coCoc ? now()->addMinutes($this->hoSo($k)->phut_giu_cho) : null);

            if (! $coCoc) {
                $this->capNhatDuNguoi($k);

                return null;
            }

            return $this->taoCoc($k, $tg, 'coc_keo_le', $k->tien_coc_ca_nhan);
        });
    }

    /** Đại diện một bên (đội đấu đội) thêm thành viên đã có tài khoản. Thành viên chịu chung uy tín của đội. */
    public function themThanhVien(User $daiDien, Keo $k, User $tv): ThamGiaKeo
    {
        $ben = $k->laDaiDien($daiDien, 'a') ? 'a' : ($k->laDaiDien($daiDien, 'b') ? 'b' : null);
        if (! $k->laDoiDau() || ! $ben || ! in_array($k->trang_thai, ['dang_tim', 'cho_coc', 'da_chot'], true)) {
            $this->loi('Chỉ đại diện đội mới thêm được thành viên, trước giờ đá.');
        }
        if ($ben === 'b' && ! $k->laKeoTruoc() && $k->trang_thai !== 'da_chot') {
            $this->loi('Bên B cần cọc xong mới thêm thành viên.');
        }
        if ($k->benCua($tv)) {
            $this->loi($tv->name.' đã ở trong kèo này.');
        }
        if ($lyDo = $tv->lyDoKhongVaoKeo(true)) {
            $this->loi($tv->name.': '.$lyDo);
        }

        return $this->ghiThamGia($k, $tv, $ben, 'thanh_vien', 'da_xac_nhan');
    }

    public function huyKeo(User $u, Keo $k): void
    {
        if (! $k->laDaiDien($u, 'a') || $k->trang_thai !== 'dang_tim') {
            $this->loi('Chỉ người tạo huỷ được kèo đang tìm.');
        }
        $daCoc = GiaoDich::whereIn('tham_gia_keo_id', $k->thamGias()->pluck('id'))->where('trang_thai', 'thanh_cong')->exists();
        if ($daCoc) {
            $this->loi('Đã có người cọc vào kèo, hãy liên hệ chủ sân để huỷ và hoàn cọc.');
        }

        DB::transaction(function () use ($k) {
            $ids = $k->thamGias()->pluck('id');
            GiaoDich::whereIn('tham_gia_keo_id', $ids)->where('trang_thai', 'cho_thanh_toan')->update(['trang_thai' => 'da_huy']);
            ThamGiaKeo::whereIn('id', $ids)->where('user_id', '!=', $k->nguoi_tao_id)->update(['trang_thai' => 'bi_huy']);
            $k->forceFill(['trang_thai' => 'da_huy'])->save();
        });
    }

    /**
     * Huỷ kèo từ phía sân (chủ sân bấm) hoặc tự động khi lịch sân bị huỷ.
     * Ai đã cọc → đánh dấu chờ hoàn; chủ sân chuyển trả rồi bấm "Đã hoàn".
     */
    public function huyKeoBoiSan(Keo $k, string $lyDo): int
    {
        if (in_array($k->trang_thai, ['hoan_tat', 'da_huy'], true)) {
            return 0;
        }

        return DB::transaction(function () use ($k, $lyDo) {
            $k = Keo::lockForUpdate()->findOrFail($k->id);
            $ids = $k->thamGias()->pluck('id');
            GiaoDich::whereIn('tham_gia_keo_id', $ids)->where('trang_thai', 'cho_thanh_toan')->update(['trang_thai' => 'da_huy']);
            $soHoan = GiaoDich::whereIn('tham_gia_keo_id', $ids)->where('trang_thai', 'thanh_cong')->where('xu_ly_hoan', 'khong')
                ->update(['xu_ly_hoan' => 'cho_hoan']);
            ThamGiaKeo::whereIn('id', $ids)->update(['trang_thai' => 'bi_huy']);
            $k->forceFill(['trang_thai' => 'da_huy', 'mo_ta' => trim(($k->mo_ta ? $k->mo_ta."\n" : '').'[Huỷ: '.$lyDo.']')])->save();
            XinKeo::where('keo_id', $k->id)->where('trang_thai', 'cho_duyet')->update(['trang_thai' => 'tu_choi']);
            // Kèo trước đang chờ cọc: đơn giữ sân do kèo tạo cũng huỷ theo
            if ($k->laKeoTruoc() && ($don = DatSan::lockForUpdate()->find($k->dat_san_id)) && $don->trang_thai === 'cho_thanh_toan') {
                $don->forceFill(['trang_thai' => 'da_huy', 'huy_luc' => now(), 'ly_do_huy' => 'Kèo bị huỷ: '.$lyDo])->save();
                DB::table('o_lich')->where('dat_san_id', $don->id)->delete();
            }

            return $soHoan;
        });
    }

    public function xacNhanDaHoanCoc(GiaoDich $gd): void
    {
        if ($gd->xu_ly_hoan !== 'cho_hoan') {
            $this->loi('Khoản này không ở trạng thái chờ hoàn.');
        }
        $gd->forceFill(['xu_ly_hoan' => 'da_hoan', 'da_hoan_luc' => now()])->save();
    }

    // ================================================================ KÈO TRƯỚC, ĐẶT SÂN SAU

    /**
     * Tạo kèo đội đấu đội KHI CHƯA ĐẶT SÂN. Khách tạo: chọn đội của mình làm bên A.
     * Chủ sân / nhân viên tạo "ô cáp kèo": chưa có bên nào, đội nào xin vào trước được duyệt làm bên A.
     * Sân chưa bị giữ, chưa ai mất tiền cho tới khi chốt đủ hai đội.
     */
    public function taoKeoTruoc(User $u, San $san, string $ngay, int $khungGioId, ?int $thoiLuong, array $d): Keo
    {
        $laSan = $this->laPhiaSanCua($u, $san);
        if (! $laSan) {
            if (! $u->laKhach()) {
                $this->loi('Chỉ tài khoản khách hoặc phía sân tạo được kèo.');
            }
            if ($ly = $u->lyDoKhongVaoKeo(true)) {
                $this->loi($ly);
            }
        }
        $hoSo = $san->chuSan->hoSoChuSan;
        if ($san->trang_thai !== 'hoat_dong' || ! $hoSo?->conHan()) {
            $this->loi('Sân này đang tạm ngưng nhận đặt.');
        }
        if (! $hoSo->ngan_hang_bin && $hoSo->phuong_thuc_tt !== 'payos') {
            $this->loi('Sân chưa cài tài khoản nhận cọc nên chưa cáp kèo online được.');
        }

        $ngayC = Carbon::parse($ngay)->startOfDay();
        $khung = app(BangGia::class)->khungGioTrongNgay($san, $ngayC)->firstWhere('id', $khungGioId);
        if (! $khung) {
            $this->loi('Khung giờ không áp dụng cho ngày này.');
        }
        $phut = app(DatSanService::class)->kiemThoiLuong($san, $khung, $thoiLuong);
        $bd = $khung->phutBatDau();
        if ($ngayC->copy()->addMinutes($bd)->lt(now()->addMinutes(self::PHUT_TOI_THIEU_TRUOC_GIO))) {
            $this->loi('Giờ đá phải còn ít nhất '.self::PHUT_TOI_THIEU_TRUOC_GIO.' phút để hai đội kịp cọc.');
        }
        if ($ngayC->gt(today()->addDays(DatSanService::SO_NGAY_DAT_TRUOC))) {
            $this->loi('Chỉ cáp kèo trước tối đa '.DatSanService::SO_NGAY_DAT_TRUOC.' ngày.');
        }
        if (! $this->conTrong($san, $ngayC->toDateString(), $bd, $bd + $phut)) {
            $this->loi('Giờ này sân đã có người đặt. Chọn giờ khác.');
        }
        $doiA = $laSan ? null : $this->doiCuaNguoi($u, $d['doi_id'] ?? null);
        if ($doiA) {
            $this->kiemTraDoiRanh($doiA, $ngayC->copy()->addMinutes($bd), $ngayC->copy()->addMinutes($bd + $phut));
        }

        $gia = $khung->giaTheoPhut($phut);
        $k = new Keo;
        $k->forceFill([
            'san_id' => $san->id, 'ngay' => $ngayC->toDateString(), 'khung_gio_id' => $khung->id, 'thoi_luong' => $phut,
            'loai' => 'doi_dau', 'nguoi_tao_id' => $u->id, 'dai_dien_a_id' => $laSan ? null : $u->id, 'doi_a_id' => $doiA?->id,
            'trinh_do' => $d['trinh_do'] ?? 'trung_binh', 'mo_ta' => $d['mo_ta'] ?? null,
            'tien_san' => $gia, 'ty_le_thua' => (int) $d['ty_le_thua'],
            'coc_moi_ben' => (int) (ceil($gia * self::TY_LE_COC_BEN_B / 100 / 1000) * 1000),
            'han_xac_nhan_luc' => $ngayC->copy()->addMinutes($bd + $phut + self::PHUT_XAC_NHAN),
            'cong_khai' => ! isset($d['cong_khai']) || (bool) $d['cong_khai'], 'ma_moi' => Str::lower(Str::random(10)),
            'trang_thai' => 'dang_tim',
        ]);
        DB::transaction(function () use ($k, $u, $laSan) {
            $k->save();
            if (! $laSan) {
                $this->ghiThamGia($k, $u, 'a', 'dai_dien', 'da_xac_nhan');
            }
        });

        return $k;
    }

    /** Đội khác xin nhận kèo (kèo trước) — chờ chủ kèo hoặc sân duyệt. */
    public function xinNhan(User $u, Keo $k, ?int $doiId, ?string $loiNhan = null): XinKeo
    {
        if (! $k->laKeoTruoc() || $k->trang_thai !== 'dang_tim' || $k->batDauLuc()->lte(now())) {
            $this->loi('Kèo này không còn nhận đội.');
        }
        if (! $u->laKhach()) {
            $this->loi('Chỉ tài khoản khách mới nhận kèo.');
        }
        if ($k->idDaiDienA() === $u->id || $k->benCua($u)) {
            $this->loi('Bạn đã ở trong kèo này.');
        }
        if ($ly = $u->lyDoKhongVaoKeo(true)) {
            $this->loi($ly);
        }
        $doi = $this->doiCuaNguoi($u, $doiId);
        if ((int) $doi->id === (int) $k->doi_a_id) {
            $this->loi('Đội này đang là chủ kèo.');
        }
        $this->kiemTraDoiRanh($doi, $k->batDauLuc(), $k->ketThucLuc(), $k->id);

        $x = XinKeo::where('keo_id', $k->id)->where('user_id', $u->id)->first() ?? new XinKeo;
        $x->forceFill(['keo_id' => $k->id, 'user_id' => $u->id, 'doi_id' => $doi->id, 'trang_thai' => 'cho_duyet', 'loi_nhan' => $loiNhan])->save();

        return $x;
    }

    /** Phía sân thêm thẳng một đội vào kèo (VD đội gọi điện tới sân). Đội trưởng đứng đại diện. */
    public function themDoiVaoKeo(User $nguoi, Keo $k, Doi $doi): Keo
    {
        if (! $k->laKeoTruoc() || ! $this->laPhiaSanCua($nguoi, $k->san)) {
            $this->loi('Chỉ chủ sân / nhân viên thêm đội vào kèo của sân.');
        }
        $this->kiemTraDoiRanh($doi, $k->batDauLuc(), $k->ketThucLuc(), $k->id);
        $dt = $doi->doiTruong;
        if ($ly = $dt->lyDoKhongVaoKeo(true)) {
            $this->loi($doi->ten.': '.$ly);
        }
        $x = XinKeo::where('keo_id', $k->id)->where('user_id', $dt->id)->first() ?? new XinKeo;
        $x->forceFill(['keo_id' => $k->id, 'user_id' => $dt->id, 'doi_id' => $doi->id, 'trang_thai' => 'cho_duyet', 'loi_nhan' => 'Sân thêm'])->save();

        return $this->duyetXin($nguoi, $x, true);
    }

    /**
     * Duyệt yêu cầu: chủ kèo (đại diện A) hoặc chủ sân / nhân viên.
     * Kèo sân tạo chưa có bên A → đội được duyệt đầu tiên làm bên A. Có đủ A và B → CHỐT: tự đặt sân
     * (giữ chỗ theo thời gian chủ sân cài) và mỗi đội cọc 15%. Từ đây ai bỏ kèo bị trừ uy tín / mất cọc.
     */
    public function duyetXin(User $nguoi, XinKeo $x, bool $dongY): Keo
    {
        $k = $x->keo;
        $laSan = $this->laPhiaSanCua($nguoi, $k->sanThuc());
        if (! $laSan && ! ($k->dai_dien_a_id && (int) $k->dai_dien_a_id === $nguoi->id)) {
            $this->loi('Chỉ chủ kèo hoặc chủ sân / nhân viên duyệt được.');
        }
        if ($x->trang_thai !== 'cho_duyet') {
            $this->loi('Yêu cầu này đã được xử lý.');
        }
        if (! $dongY) {
            $x->forceFill(['trang_thai' => 'tu_choi'])->save();

            return $k;
        }

        return DB::transaction(function () use ($x) {
            $k = Keo::lockForUpdate()->findOrFail($x->keo_id);
            if ($k->trang_thai !== 'dang_tim' || $k->batDauLuc()->lte(now())) {
                $this->loi('Kèo không còn nhận đội.');
            }
            $u = $x->user;
            if (! $k->dai_dien_a_id) {
                $k->forceFill(['dai_dien_a_id' => $u->id, 'doi_a_id' => $x->doi_id])->save();
                $this->ghiThamGia($k, $u, 'a', 'dai_dien', 'da_xac_nhan');
                $x->forceFill(['trang_thai' => 'da_duyet'])->save();

                return $k;
            }

            $k->forceFill(['dai_dien_b_id' => $u->id, 'doi_b_id' => $x->doi_id])->save();
            $this->ghiThamGia($k, $u, 'b', 'dai_dien', 'da_xac_nhan');
            $x->forceFill(['trang_thai' => 'da_duyet'])->save();
            XinKeo::where('keo_id', $k->id)->where('id', '!=', $x->id)->where('trang_thai', 'cho_duyet')->update(['trang_thai' => 'tu_choi']);

            return $this->chotKeoTruoc($k);
        });
    }

    /** Scheduler: kèo đã chốt mà hết giờ cọc → huỷ kèo, nhả sân; đội đã cọc chờ hoàn, đội chưa cọc bị trừ uy tín. */
    public function xuLyKeoTruocQuaHan(): int
    {
        $so = 0;
        Keo::where('trang_thai', 'cho_coc')->where('han_coc_luc', '<', now())->pluck('id')->each(function ($id) use (&$so) {
            DB::transaction(function () use ($id, &$so) {
                $k = Keo::lockForUpdate()->find($id);
                if (! $k || $k->trang_thai !== 'cho_coc' || $k->han_coc_luc?->isFuture()) {
                    return;
                }
                foreach ($k->thamGias()->where('vai_tro', 'dai_dien')->with('user')->get() as $tg) {
                    if ($tg->trang_thai === 'da_xac_nhan') {
                        GiaoDich::where('tham_gia_keo_id', $tg->id)->where('trang_thai', 'thanh_cong')->update(['xu_ly_hoan' => 'cho_hoan']);
                    } elseif ($tg->trang_thai === 'cho_coc') {
                        $nguoi = User::lockForUpdate()->find($tg->user_id);
                        $nguoi->forceFill(['diem_uy_tin' => max(0, $nguoi->diem_uy_tin - self::PHAT_BUNG_KEO)])->save();
                        $this->ghiLichSu($nguoi, -self::PHAT_BUNG_KEO, 'bung_keo', $k, $tg, 'Không cọc sau khi chốt kèo');
                    }
                }
                $ids = $k->thamGias()->pluck('id');
                GiaoDich::whereIn('tham_gia_keo_id', $ids)->where('trang_thai', 'cho_thanh_toan')->update(['trang_thai' => 'het_han']);
                ThamGiaKeo::whereIn('id', $ids)->update(['trang_thai' => 'bi_huy']);
                $k->forceFill(['trang_thai' => 'da_huy', 'mo_ta' => trim(($k->mo_ta ? $k->mo_ta."\n" : '').'[Huỷ: hết giờ cọc sau khi chốt]')])->save();

                if ($don = DatSan::lockForUpdate()->find($k->dat_san_id)) {
                    if ($don->trang_thai === 'cho_thanh_toan') {
                        $don->forceFill(['trang_thai' => 'da_huy', 'huy_luc' => now(), 'ly_do_huy' => 'Kèo không đủ cọc'])->save();
                    }
                    DB::table('o_lich')->where('dat_san_id', $don->id)->delete();
                }
                $so++;
            });
        });

        return $so;
    }

    private function chotKeoTruoc(Keo $k): Keo
    {
        $san = $k->san;
        $hoSo = $san->chuSan->hoSoChuSan;
        $den = now()->addMinutes($hoSo->phut_giu_cho);
        $bd = $k->khungGio->phutBatDau();

        $don = app(DatSanService::class)->ghiDon($san, $k->ngay->toDateString(), $bd, $bd + $k->thoi_luong, [
            'khach_id' => $k->dai_dien_a_id, 'khung_gio_id' => $k->khung_gio_id, 'gia_chot' => $k->tien_san,
            'tien_coc' => $k->coc_moi_ben * 2, 'nguon' => 'online', 'trang_thai' => 'cho_thanh_toan', 'giu_cho_den' => $den,
            'ghi_chu' => 'Kèo #'.$k->id.' — mỗi đội cọc '.number_format($k->coc_moi_ben, 0, ',', '.').'đ',
        ]);
        $k->forceFill(['dat_san_id' => $don->id, 'trang_thai' => 'cho_coc', 'han_coc_luc' => $den])->save();
        $k->setRelation('datSan', $don);

        foreach (['a', 'b'] as $ben) {
            $tg = $k->thamGias()->where('ben', $ben)->where('vai_tro', 'dai_dien')->firstOrFail();
            $tg->forceFill(['trang_thai' => 'cho_coc', 'giu_cho_den' => $den])->save();
            $this->taoCoc($k, $tg, 'coc_keo_doi', $k->coc_moi_ben);
        }

        return $k;
    }

    private function conTrong(San $san, string $ngay, int $bd, int $kt): bool
    {
        return DB::table('o_lich')->whereIn('san_id', array_merge([$san->id], $san->idsSanCon()))->where('ngay', $ngay)
            ->whereBetween('o', [intdiv($bd, ThoiGian::BUOC_PHUT), intdiv($kt, ThoiGian::BUOC_PHUT) - 1])->doesntExist();
    }

    /**
     * Đội của người dùng cho kèo đội đấu đội — BẮT BUỘC có đội (để đối thủ xem trước thành tích, uy tín).
     * Có đúng 1 đội mà không chọn → tự lấy đội đó; nhiều đội → phải chọn.
     */
    private function doiCuaNguoi(User $u, $doiId, bool $batBuoc = true): ?Doi
    {
        if (! $doiId) {
            $ds = $u->doiDangChoi()->get();
            if ($ds->count() === 1) {
                return $ds->first();
            }
            $this->loi($ds->isEmpty()
                ? 'Bạn cần tạo hoặc vào một đội trước khi cáp kèo đội đấu đội (để đối thủ xem được đội bạn).'
                : 'Bạn có nhiều đội — chọn đội sẽ đá kèo này.');
        }
        $doi = Doi::find($doiId);
        if (! $doi || ! $doi->laThanhVien($u)) {
            $this->loi('Bạn không ở trong đội đã chọn.');
        }

        return $doi;
    }

    /** Một đội không được ở 2 kèo trùng giờ (kể cả ở 2 sân khác nhau). */
    private function kiemTraDoiRanh(Doi $doi, Carbon $bd, Carbon $kt, ?int $truKeoId = null): void
    {
        $trung = Keo::with(['datSan', 'khungGio'])
            ->where(fn ($q) => $q->where('doi_a_id', $doi->id)->orWhere('doi_b_id', $doi->id))
            ->whereNotIn('trang_thai', ['hoan_tat', 'da_huy'])
            ->when($truKeoId, fn ($q) => $q->where('id', '!=', $truKeoId))
            ->get()
            ->first(fn (Keo $k) => $k->batDauLuc()->lt($kt) && $bd->lt($k->ketThucLuc()));
        if ($trung) {
            $this->loi($doi->ten.' đã có kèo #'.$trung->id.' lúc '.$trung->batDauLuc()->format('H:i d/m').' trùng giờ này.');
        }
    }

    private function laPhiaSanCua(User $u, San $san): bool
    {
        return $u->idChuSanQuanLy() !== null && (int) $san->chu_san_id === $u->idChuSanQuanLy();
    }

    // ================================================================ CỌC KÈO

    /** Chủ sân / nhân viên bấm "Đã nhận cọc" (cách A) — hoặc cổng gọi qua nhanTienCocKeo (cách B). */
    public function xacNhanCocKeo(GiaoDich $gd, ?User $nguoi): void
    {
        DB::transaction(function () use ($gd, $nguoi) {
            $gd = GiaoDich::lockForUpdate()->findOrFail($gd->id);
            if ($gd->trang_thai !== 'cho_thanh_toan') {
                $this->loi('Khoản cọc này không còn chờ xác nhận.');
            }
            $gd->forceFill(['trang_thai' => 'thanh_cong', 'thanh_toan_luc' => now(), 'xac_nhan_boi_id' => $nguoi?->id])->save();
            $this->kichHoatThamGia($gd);
        });
    }

    public function nhanTienCocKeo(GiaoDich $gd, int $soTien, string $thamChieu, array $duLieu): string
    {
        return DB::transaction(function () use ($gd, $soTien, $thamChieu, $duLieu) {
            $gd = GiaoDich::lockForUpdate()->findOrFail($gd->id);
            if ($gd->trang_thai === 'thanh_cong') {
                return 'da_xu_ly_truoc';
            }
            if ($soTien !== (int) $gd->so_tien) {
                return 'sai_so_tien';
            }
            $choCoc = $gd->trang_thai === 'cho_thanh_toan';
            $gd->forceFill([
                'trang_thai' => 'thanh_cong', 'thanh_toan_luc' => now(), 'ma_tham_chieu' => $thamChieu,
                'du_lieu_cong' => array_merge((array) $gd->du_lieu_cong, ['webhook' => $duLieu]),
                'xu_ly_hoan' => $choCoc ? 'khong' : 'cho_hoan',
            ])->save();

            if (! $choCoc) {
                return 'tien_ve_sau_khi_huy'; // chỗ đã nhả → chủ sân hoàn lại
            }
            $this->kichHoatThamGia($gd);

            return 'da_xac_nhan';
        });
    }

    public const PHUT_GIU_SAU_BAO_CHUYEN = 120;
    public const PHAT_BAO_SAI = 5;

    /** Người cọc kèo bấm "Tôi đã chuyển khoản": 1 lần, giữ thêm 2 tiếng, email chủ sân + nhân viên. */
    public function baoDaChuyenCoc(GiaoDich $gd, User $u): GiaoDich
    {
        $gd = DB::transaction(function () use ($gd, $u) {
            $gd = GiaoDich::lockForUpdate()->findOrFail($gd->id);
            if ((int) $gd->nguoi_tra_id !== $u->id || $gd->trang_thai !== 'cho_thanh_toan' || ! $gd->tham_gia_keo_id) {
                $this->loi('Khoản cọc này không còn chờ chuyển.');
            }
            if ($gd->bao_da_chuyen_luc) {
                $this->loi('Bạn đã báo chuyển khoản rồi. Chủ sân đang kiểm tra.');
            }
            $den = now()->addMinutes(self::PHUT_GIU_SAU_BAO_CHUYEN);
            $gd->forceFill(['bao_da_chuyen_luc' => now()])->save();

            $tg = ThamGiaKeo::lockForUpdate()->findOrFail($gd->tham_gia_keo_id);
            if (! $tg->giu_cho_den || $tg->giu_cho_den->lt($den)) {
                $tg->forceFill(['giu_cho_den' => $den])->save();
            }
            // Kèo trước đang chờ cọc: kéo dài hạn cọc của kèo và giữ sân của đơn theo
            $k = Keo::lockForUpdate()->findOrFail($tg->keo_id);
            if ($k->trang_thai === 'cho_coc' && $k->han_coc_luc?->lt($den)) {
                $k->forceFill(['han_coc_luc' => $den])->save();
                DatSan::whereKey($k->dat_san_id)->where('trang_thai', 'cho_thanh_toan')->update(['giu_cho_den' => $den]);
            }

            return $gd;
        });

        $chuId = (int) $gd->chu_san_id;
        User::where(fn ($q) => $q->where('id', $chuId)->orWhere(fn ($q) => $q->where('chu_san_id', $chuId)->where('vai_tro', User::NHAN_VIEN)))
            ->where('trang_thai', 'hoat_dong')->get()
            ->each(fn (User $x) => $x->notify(new \App\Notifications\KhachBaoDaChuyenCoc($gd)));

        return $gd;
    }

    /** Phía sân không thấy tiền cọc kèo đã được báo chuyển → người đó mất chỗ trong kèo + trừ uy tín. */
    public function khongThayTienCoc(GiaoDich $gd, User $nguoi): void
    {
        if ($gd->trang_thai !== 'cho_thanh_toan' || ! $gd->bao_da_chuyen_luc || ! $gd->tham_gia_keo_id) {
            $this->loi('Chỉ dùng cho khoản cọc đã được báo chuyển mà chưa xác nhận.');
        }
        DB::transaction(function () use ($gd) {
            $tg = ThamGiaKeo::lockForUpdate()->findOrFail($gd->tham_gia_keo_id);
            $k = Keo::lockForUpdate()->findOrFail($tg->keo_id);
            $u = User::lockForUpdate()->findOrFail($gd->nguoi_tra_id);

            $u->forceFill(['diem_uy_tin' => max(0, $u->diem_uy_tin - self::PHAT_BAO_SAI)])->save();
            $this->ghiLichSu($u, -self::PHAT_BAO_SAI, 'bao_chuyen_sai', $k, $tg, 'Cọc '.$gd->noi_dung_ck);
            $soLan = DB::table('lich_su_uy_tin')->where('user_id', $u->id)->where('ly_do', 'bao_chuyen_sai')
                ->where('created_at', '>=', now()->subDays(DatSanService::NGAY_CAM))->count();
            if ($soLan >= 2) {
                $u->forceFill(['cam_dat_den' => now()->addDays(DatSanService::NGAY_CAM), 'ly_do_cam_dat' => 'báo chuyển khoản sai 2 lần trong 30 ngày'])->save();
            }

            if ($k->trang_thai === 'cho_coc') {
                // Kèo trước: bên này bị loại (đã phạt báo sai, không phạt thêm bùng kèo) → huỷ kèo, nhả sân, bên kia được hoàn
                $tg->forceFill(['trang_thai' => 'bi_huy'])->save();
                $k->forceFill(['han_coc_luc' => now()->subSecond()])->save();
            } else {
                $tg->forceFill(['giu_cho_den' => now()->subSecond()])->save();
            }
        });
        $this->nhaCocQuaHan();
        $this->xuLyKeoTruocQuaHan();
    }

    /** Scheduler: nhả người chưa cọc quá hạn; kèo đối đầu quay lại "đang tìm". */
    public function nhaCocQuaHan(): int
    {
        $so = 0;
        ThamGiaKeo::where('trang_thai', 'cho_coc')->where('giu_cho_den', '<', now())
            ->whereHas('keo', fn ($q) => $q->where('trang_thai', '!=', 'cho_coc'))->pluck('id')->each(function ($id) use (&$so) {
            DB::transaction(function () use ($id, &$so) {
                $tg = ThamGiaKeo::lockForUpdate()->find($id);
                if (! $tg || $tg->trang_thai !== 'cho_coc' || $tg->giu_cho_den?->isFuture()) {
                    return;
                }
                $tg->forceFill(['trang_thai' => 'bi_huy'])->save();
                GiaoDich::where('tham_gia_keo_id', $id)->where('trang_thai', 'cho_thanh_toan')->update(['trang_thai' => 'het_han']);
                $k = Keo::lockForUpdate()->find($tg->keo_id);
                if ($k && (int) $k->dai_dien_b_id === (int) $tg->user_id) {
                    $k->forceFill(['dai_dien_b_id' => null, 'trang_thai' => 'dang_tim'])->save();
                }
                $so++;
            });
        });

        return $so;
    }

    // ================================================================ SAU TRẬN

    public function baoKetQua(User $u, Keo $k, string $ketQua): Keo
    {
        $ben = $k->laDaiDien($u, 'a') ? 'a' : ($k->laDaiDien($u, 'b') ? 'b' : null);
        if (! $k->laDoiDau() || ! $ben) {
            $this->loi('Chỉ đại diện hai đội báo kết quả.');
        }
        if (! in_array($k->trang_thai, ['da_chot', 'cho_xac_nhan', 'tranh_chap'], true) || ! $k->datSan || $k->datSan->batDauLuc()->gt(now())) {
            $this->loi('Chưa thể báo kết quả lúc này.');
        }

        return DB::transaction(function () use ($k, $ben, $ketQua) {
            $k = Keo::lockForUpdate()->findOrFail($k->id);
            $k->forceFill([$ben.'_bao_ket_qua' => $ketQua, $ben.'_bao_luc' => now()]);

            if ($k->a_bao_ket_qua && $k->b_bao_ket_qua) {
                if ($k->a_bao_ket_qua === $k->b_bao_ket_qua) {
                    $this->chot($k, $k->a_bao_ket_qua, null);

                    return $k;
                }
                $k->trang_thai = 'tranh_chap';
            } elseif ($k->trang_thai !== 'tranh_chap') {
                $k->trang_thai = 'cho_xac_nhan';
            }
            $k->save();

            return $k;
        });
    }

    /** Chủ sân / nhân viên giám sát chốt kết quả (tranh chấp, hoặc thay cho đội không báo). */
    public function chotKetQua(User $nguoi, Keo $k, string $ketQua): Keo
    {
        if (in_array($k->trang_thai, ['hoan_tat', 'da_huy'], true) || ! $k->laDoiDau() || ! $k->datSan || $k->datSan->batDauLuc()->gt(now())) {
            $this->loi('Không chốt được kết quả kèo này.');
        }

        return DB::transaction(fn () => $this->chot(Keo::lockForUpdate()->findOrFail($k->id), $ketQua, $nguoi));
    }

    /** Scheduler: quá 60 phút sau giờ kết thúc mà bên nào chưa báo kết quả → hạn chế bên đó. */
    public function kiemTraHanXacNhan(): int
    {
        $so = 0;
        Keo::where('loai', 'doi_dau')->whereIn('trang_thai', ['da_chot', 'cho_xac_nhan'])
            ->where('han_xac_nhan_luc', '<', now())->get()
            ->each(function (Keo $k) use (&$so) {
                DB::transaction(function () use ($k, &$so) {
                    foreach (['a', 'b'] as $ben) {
                        if ($k->{$ben.'_bao_ket_qua'}) {
                            continue;
                        }
                        foreach ($k->thanhVien($ben)->where('trang_thai', 'da_xac_nhan')->with('user')->get() as $tg) {
                            $tg->user->forceFill([
                                'han_che_luc' => now(),
                                'ly_do_han_che' => "Không xác nhận kết quả kèo #{$k->id} trong ".self::PHUT_XAC_NHAN.' phút',
                            ])->save();
                            $this->ghiLichSu($tg->user, 0, 'khong_xac_nhan_ket_qua', $k, $tg);
                            $so++;
                        }
                    }
                    $k->forceFill(['trang_thai' => 'tranh_chap'])->save(); // chờ chủ sân chốt
                });
            });

        return $so;
    }

    /** Đội đấu đội: điểm danh cả một bên. Vắng → trừ uy tín TẤT CẢ thành viên bên đó. */
    public function diemDanhBen(Keo $k, string $ben, bool $coMat, User $nguoi): void
    {
        $this->kiemTraDiemDanh($k, $nguoi);
        DB::transaction(function () use ($k, $ben, $coMat, $nguoi) {
            foreach ($k->thanhVien($ben)->where('trang_thai', 'da_xac_nhan')->get() as $tg) {
                $this->doiDiemDanh($tg, $coMat ? 'co_mat' : 'vang', $k);
            }
            $k->forceFill(['diem_danh_luc' => now(), 'diem_danh_boi_id' => $nguoi->id])->save();
        });
    }

    /** Ghép lẻ: điểm danh từng người. */
    public function diemDanhNguoi(ThamGiaKeo $tg, bool $coMat, User $nguoi): void
    {
        $k = $tg->keo;
        $this->kiemTraDiemDanh($k, $nguoi);
        DB::transaction(function () use ($tg, $coMat, $k, $nguoi) {
            $this->doiDiemDanh($tg, $coMat ? 'co_mat' : 'vang', $k);
            $k->forceFill(['diem_danh_luc' => now(), 'diem_danh_boi_id' => $nguoi->id])->save();
        });
    }

    /** Chủ sân miễn phạt người thực tế có đến (VD đội vắng nhưng vài người vẫn tới). */
    public function mienPhat(ThamGiaKeo $tg, User $chuSan): void
    {
        if ($tg->diem_danh !== 'vang' || $tg->mien_phat) {
            $this->loi('Người này không bị tính vắng.');
        }
        DB::transaction(function () use ($tg, $chuSan) {
            $u = User::lockForUpdate()->findOrFail($tg->user_id);
            $u->forceFill([
                'diem_uy_tin' => min(100, $u->diem_uy_tin + self::PHAT_VANG),
                'so_tran_vang' => max(0, $u->so_tran_vang - 1),
                'so_tran_tham_gia' => $u->so_tran_tham_gia + 1,
            ])->save();
            $tg->forceFill(['mien_phat' => true, 'mien_phat_boi_id' => $chuSan->id, 'diem_danh' => 'co_mat'])->save();
            $this->ghiLichSu($u, self::PHAT_VANG, 'chu_san_dieu_chinh', $tg->keo, $tg, 'Chủ sân miễn phạt vắng');
            $this->capNhatKhoaKeo($u);
        });
    }

    /**
     * Quyết toán tiền sân đội đấu đội. "con" âm = sân trả lại cho bên đó.
     *
     * @return array{a: array, b: array}|null
     */
    public function quyetToan(Keo $k): ?array
    {
        if (! $k->laDoiDau() || ! $k->ket_qua) {
            return null;
        }
        $don = $k->datSan;
        $tyLeThua = $k->ket_qua === 'hoa' ? 50 : $k->ty_le_thua;
        $phanThua = (int) round($k->tien_san * $tyLeThua / 100);
        $benThua = ['a_thang' => 'b', 'b_thang' => 'a', 'hoa' => null][$k->ket_qua];

        $daTra = [
            'a' => $k->laKeoTruoc()
                ? GiaoDich::whereIn('tham_gia_keo_id', $k->thanhVien('a')->pluck('id'))->where('trang_thai', 'thanh_cong')->sum('so_tien')
                : ($don->xac_nhan_coc_luc ? $don->tien_coc : 0),
            'b' => GiaoDich::whereIn('tham_gia_keo_id', $k->thanhVien('b')->pluck('id'))->where('trang_thai', 'thanh_cong')->sum('so_tien'),
        ];
        $kq = [];
        foreach (['a', 'b'] as $ben) {
            $phan = $benThua === null ? (int) round($k->tien_san / 2) : ($ben === $benThua ? $phanThua : $k->tien_san - $phanThua);
            $kq[$ben] = ['phan' => $phan, 'da_tra' => (int) $daTra[$ben], 'con' => $phan - (int) $daTra[$ben]];
        }

        return $kq;
    }

    public function goHanChe(User $u): void
    {
        $u->forceFill(['han_che_luc' => null, 'ly_do_han_che' => null, 'cam_dat_den' => null, 'ly_do_cam_dat' => null])->save();
    }

    // ================================================================ NỘI BỘ

    private function chot(Keo $k, string $ketQua, ?User $nguoi): Keo
    {
        $k->forceFill([
            'ket_qua' => $ketQua, 'ket_qua_chot_boi_id' => $nguoi?->id,
            'trang_thai' => 'hoan_tat', 'hoan_tat_luc' => now(),
        ])->save();

        // Đã có kết quả = trận đã diễn ra → ai chưa điểm danh coi như có mặt
        foreach ($k->thanhVien()->where('trang_thai', 'da_xac_nhan')->where('diem_danh', 'chua')->get() as $tg) {
            $this->doiDiemDanh($tg, 'co_mat', $k);
        }

        return $k;
    }

    private function doiDiemDanh(ThamGiaKeo $tg, string $moi, Keo $k): void
    {
        $cu = $tg->diem_danh;
        if ($cu === $moi) {
            return;
        }
        $u = User::lockForUpdate()->findOrFail($tg->user_id);
        $diem = $u->diem_uy_tin;
        $di = $u->so_tran_tham_gia;
        $vang = $u->so_tran_vang;

        // Gỡ tác động cũ
        if ($cu === 'co_mat') { $di--; $diem -= self::THUONG_DI; }
        if ($cu === 'vang') { $vang--; $diem += self::PHAT_VANG; }
        // Áp tác động mới
        if ($moi === 'co_mat') { $di++; $diem += self::THUONG_DI; }
        if ($moi === 'vang') { $vang++; $diem -= self::PHAT_VANG; }

        $u->forceFill(['diem_uy_tin' => max(0, min(100, $diem)), 'so_tran_tham_gia' => max(0, $di), 'so_tran_vang' => max(0, $vang)])->save();
        $tg->forceFill(['diem_danh' => $moi])->save();
        $this->ghiLichSu($u, $moi === 'vang' ? -self::PHAT_VANG : self::THUONG_DI, $moi === 'vang' ? 'vang_mat' : 'di_du', $k, $tg);
        $this->capNhatKhoaKeo($u);
    }

    /** Vắng (chưa được miễn) >= 3 lần trong 30 ngày → khoá vào kèo 30 ngày; ít hơn → gỡ khoá. */
    private function capNhatKhoaKeo(User $u): void
    {
        $soVang = ThamGiaKeo::where('user_id', $u->id)->where('diem_danh', 'vang')->where('mien_phat', false)
            ->where('updated_at', '>=', now()->subDays(self::NGAY_XET_VANG))->count();

        if ($soVang >= self::SO_VANG_KHOA) {
            if (! $u->khoa_keo_den || $u->khoa_keo_den->isPast()) {
                $u->forceFill(['khoa_keo_den' => now()->addDays(self::NGAY_KHOA_KEO)])->save();
            }
        } elseif ($u->khoa_keo_den) {
            $u->forceFill(['khoa_keo_den' => null])->save();
        }
    }

    private function laPhiaSan(User $u, DatSan $don): bool
    {
        return $u->idChuSanQuanLy() !== null && (int) $don->san->chu_san_id === $u->idChuSanQuanLy();
    }

    private function kiemTraDiemDanh(Keo $k, User $nguoi): void
    {
        $laSan = $this->laPhiaSan($nguoi, $k->datSan);
        if (! $laSan && ! $k->laDaiDien($nguoi, 'a')) {
            $this->loi('Chỉ chủ sân / nhân viên hoặc người tạo kèo điểm danh.');
        }
        if ($k->datSan->batDauLuc()->gt(now())) {
            $this->loi('Chưa tới giờ đá.');
        }
    }

    private function kiemTraVao(User $u, Keo $k, string $loai, bool $coCoc): void
    {
        if ($k->loai !== $loai || $k->trang_thai !== 'dang_tim' || $k->batDauLuc()->lte(now())) {
            $this->loi('Kèo này không còn nhận người.');
        }
        if (! $u->laKhach()) {
            $this->loi('Chỉ tài khoản khách mới tham gia kèo.');
        }
        if ($k->benCua($u)) {
            $this->loi('Bạn đã ở trong kèo này.');
        }
        if ($lyDo = $u->lyDoKhongVaoKeo($coCoc)) {
            $this->loi($lyDo);
        }
    }

    private function ghiThamGia(Keo $k, User $u, string $ben, string $vaiTro, string $trangThai, $giuChoDen = null): ThamGiaKeo
    {
        // Người từng rời / bị huỷ được vào lại: dùng lại dòng cũ (UNIQUE keo_id + user_id)
        $tg = ThamGiaKeo::where('keo_id', $k->id)->where('user_id', $u->id)->first() ?? new ThamGiaKeo;
        $tg->forceFill(['keo_id' => $k->id, 'user_id' => $u->id,'ben' => $ben, 'vai_tro' => $vaiTro, 'trang_thai' => $trangThai, 'diem_danh' => 'chua',
            'mien_phat' => false, 'giu_cho_den' => $giuChoDen])->save();

        return $tg;
    }

    private function taoCoc(Keo $k, ThamGiaKeo $tg, string $loai, int $soTien): GiaoDich
    {
        $hoSo = $this->hoSo($k);
        $dungCong = $hoSo->phuong_thuc_tt === 'payos' && $hoSo->payos_client_id && $hoSo->payos_api_key && $hoSo->payos_checksum_key;

        $gd = new GiaoDich;
        $gd->forceFill([
            'loai' => $loai, 'tham_gia_keo_id' => $tg->id, 'chu_san_id' => $k->sanThuc()->chu_san_id,
            'nguoi_tra_id' => $tg->user_id, 'phuong_thuc' => $dungCong ? 'payos' : 'vietqr',
            'noi_dung_ck' => 'T'.Str::upper(Str::random(20)), 'so_tien' => $soTien, 'trang_thai' => 'cho_thanh_toan',
        ])->save();
        $gd->forceFill(['noi_dung_ck' => sprintf('DK%06d', $gd->id)])->save();   // nội dung chuyển khoản: DK000012

        if ($dungCong) {
            try {
                $kq = $this->quanLyCong->cong('payos')->taoThanhToan($gd, $gd->noi_dung_ck, $tg->giu_cho_den->getTimestamp(), $hoSo, route('keo.coc', $gd));
                $gd->forceFill(['ma_cong' => $kq['ma_cong'], 'du_lieu_cong' => $kq])->save();
            } catch (\Throwable $e) {
                report($e);
                $this->loi('Cổng thanh toán của sân đang lỗi, vui lòng thử lại sau.'); // transaction bên ngoài sẽ huỷ mọi thứ
            }
        }

        return $gd;
    }

    private function kichHoatThamGia(GiaoDich $gd): void
    {
        $tg = ThamGiaKeo::lockForUpdate()->findOrFail($gd->tham_gia_keo_id);
        $tg->forceFill(['trang_thai' => 'da_xac_nhan', 'giu_cho_den' => null])->save();
        $k = Keo::lockForUpdate()->findOrFail($tg->keo_id);

        if ($k->trang_thai === 'cho_coc') {
            // Kèo trước: đủ cả hai đội cọc → kèo chốt, đơn đặt sân chuyển "Đã đặt"
            $conCho = $k->thamGias()->where('vai_tro', 'dai_dien')->where('trang_thai', '!=', 'da_xac_nhan')->exists();
            if (! $conCho) {
                $k->forceFill(['trang_thai' => 'da_chot', 'han_coc_luc' => null])->save();
                DatSan::whereKey($k->dat_san_id)->where('trang_thai', 'cho_thanh_toan')->update([
                    'trang_thai' => 'da_dat', 'xac_nhan_coc_luc' => now(), 'xac_nhan_coc_boi_id' => $gd->xac_nhan_boi_id, 'giu_cho_den' => null,
                ]);
            }
        } elseif ($gd->loai === 'coc_keo_doi') {
            $k->forceFill(['trang_thai' => 'da_chot'])->save();
        } else {
            $this->capNhatDuNguoi($k);
        }
    }

    private function capNhatDuNguoi(Keo $k): void
    {
        $daXacNhan = $k->thamGias()->where('vai_tro', 'nguoi_le')->where('trang_thai', 'da_xac_nhan')->count();
        if ($daXacNhan >= $k->so_nguoi_can && $k->trang_thai === 'dang_tim') {
            $k->forceFill(['trang_thai' => 'da_chot'])->save();
        }
    }

    private function soNguoiLe(Keo $k): int
    {
        return $k->thamGias()->where('vai_tro', 'nguoi_le')->whereIn('trang_thai', ['cho_coc', 'da_xac_nhan'])->count();
    }

    private function hoSo(Keo $k)
    {
        return $k->sanThuc()->chuSan->hoSoChuSan;
    }

    private function ghiLichSu(User $u, int $thayDoi, string $lyDo, Keo $k, ?ThamGiaKeo $tg, ?string $ghiChu = null): void
    {
        DB::table('lich_su_uy_tin')->insert([
            'user_id' => $u->id, 'thay_doi' => $thayDoi, 'ly_do' => $lyDo, 'ghi_chu' => $ghiChu,
            'keo_id' => $k->id, 'tham_gia_keo_id' => $tg?->id, 'created_at' => now(),
        ]);
    }

    private function loi(string $s): never
    {
        throw ValidationException::withMessages(['keo' => $s]);
    }
}
