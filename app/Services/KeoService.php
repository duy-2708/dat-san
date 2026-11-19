<?php

namespace App\Services;

use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\Keo;
use App\Models\ThamGiaKeo;
use App\Models\User;
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

    public function __construct(private QuanLyCong $quanLyCong)
    {
    }

    // ================================================================ TẠO / THAM GIA

    public function taoKeo(User $u, DatSan $don, array $d): Keo
    {
        if ((int) $don->khach_id !== $u->id || $don->trang_thai !== 'da_dat' || $don->batDauLuc()->lte(now())) {
            $this->loi('Chỉ tạo kèo cho lịch đã đặt (đã cọc) của bạn và chưa tới giờ đá.');
        }
        if ($lyDo = $u->lyDoKhongVaoKeo(true)) {
            $this->loi($lyDo);
        }

        $k = new Keo;
        $k->forceFill([
            'dat_san_id' => $don->id, 'loai' => $d['loai'], 'nguoi_tao_id' => $u->id,
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
            DB::transaction(function () use ($k, $u) {
                $k->save();
                $this->ghiThamGia($k, $u, 'a', 'dai_dien', 'da_xac_nhan');
            });
        } catch (UniqueConstraintViolationException) {
            $this->loi('Lịch này đã có kèo.');
        }

        return $k;
    }

    /** Đội đấu đội: nhận kèo làm bên B → phải cọc 15% trong thời gian giữ chỗ. */
    public function nhanKeo(User $u, Keo $k): GiaoDich
    {
        $this->kiemTraVao($u, $k, 'doi_dau', true);

        return DB::transaction(function () use ($u, $k) {
            $k = Keo::lockForUpdate()->findOrFail($k->id);
            if ($k->dai_dien_b_id) {
                $this->loi('Kèo vừa có đội khác nhận.');
            }
            $phut = $this->hoSo($k)->phut_giu_cho;
            $tg = $this->ghiThamGia($k, $u, 'b', 'dai_dien', 'cho_coc', now()->addMinutes($phut));
            $k->forceFill(['dai_dien_b_id' => $u->id])->save();

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
        if (! $k->laDoiDau() || ! $ben || ! in_array($k->trang_thai, ['dang_tim', 'da_chot'], true)) {
            $this->loi('Chỉ đại diện đội mới thêm được thành viên, trước giờ đá.');
        }
        if ($ben === 'b' && $k->trang_thai !== 'da_chot') {
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
                'du_lieu_cong' => array_merge((array) $gd->du_lieu_cong, ['webhook' => $duLieu], $choCoc ? [] : ['can_hoan' => true]),
            ])->save();

            if (! $choCoc) {
                return 'tien_ve_sau_khi_huy'; // chỗ đã nhả → chủ sân hoàn lại
            }
            $this->kichHoatThamGia($gd);

            return 'da_xac_nhan';
        });
    }

    /** Scheduler: nhả người chưa cọc quá hạn; kèo đối đầu quay lại "đang tìm". */
    public function nhaCocQuaHan(): int
    {
        $so = 0;
        ThamGiaKeo::where('trang_thai', 'cho_coc')->where('giu_cho_den', '<', now())->pluck('id')->each(function ($id) use (&$so) {
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
        if (! in_array($k->trang_thai, ['da_chot', 'cho_xac_nhan', 'tranh_chap'], true) || $k->datSan->batDauLuc()->gt(now())) {
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
        if (in_array($k->trang_thai, ['hoan_tat', 'da_huy'], true) || ! $k->laDoiDau() || $k->datSan->batDauLuc()->gt(now())) {
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
            'a' => $don->xac_nhan_coc_luc ? $don->tien_coc : 0,
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
        $u->forceFill(['han_che_luc' => null, 'ly_do_han_che' => null])->save();
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

    private function kiemTraDiemDanh(Keo $k, User $nguoi): void
    {
        $laSan = $nguoi->idChuSanQuanLy() !== null && (int) $k->datSan->san->chu_san_id === $nguoi->idChuSanQuanLy();
        if (! $laSan && ! $k->laDaiDien($nguoi, 'a')) {
            $this->loi('Chỉ chủ sân / nhân viên hoặc người tạo kèo điểm danh.');
        }
        if ($k->datSan->batDauLuc()->gt(now())) {
            $this->loi('Chưa tới giờ đá.');
        }
    }

    private function kiemTraVao(User $u, Keo $k, string $loai, bool $coCoc): void
    {
        if ($k->loai !== $loai || $k->trang_thai !== 'dang_tim' || $k->datSan->batDauLuc()->lte(now())) {
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
            'loai' => $loai, 'tham_gia_keo_id' => $tg->id, 'chu_san_id' => $k->datSan->san->chu_san_id,
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

        if ($gd->loai === 'coc_keo_doi') {
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
        return $k->datSan->san->chuSan->hoSoChuSan;
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
