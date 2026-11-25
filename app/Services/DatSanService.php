<?php

namespace App\Services;

use App\Support\BaoTin;
use App\Support\Tien;

use App\Models\ChuoiCoDinh;
use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\KhungGio;
use App\Models\San;
use App\Models\User;
use App\Notifications\DatSanThanhCong;
use App\Payments\QuanLyCong;
use App\Support\ThoiGian;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Toàn bộ nghiệp vụ đặt sân. Controller chỉ gọi vào đây.
 *
 * Chống đặt trùng: mỗi đơn chiếm các "ô 15 phút" trong bảng o_lich, cho CHÍNH sân đó và mọi sân CON của nó.
 * (Đặt sân 7 ghép từ 5A+5B → chiếm ô 7, 5A, 5B. Đặt 5A → chỉ chiếm 5A; sau đó đặt sân 7 sẽ đụng ô 5A.)
 * Bảng o_lich có UNIQUE(san_id, ngay, o) → hai người bấm cùng lúc thì MySQL chỉ cho 1 người thành công,
 * người kia nhận UniqueConstraintViolationException và cả transaction bị huỷ.
 */
class DatSanService
{
    public const SO_NGAY_DAT_TRUOC = 30;
    public const SO_DON_CHO_TOI_DA = 2;   // một khách giữ tối đa 2 ô chưa cọc cùng lúc

    public function __construct(private BangGia $bangGia, private QuanLyCong $quanLyCong)
    {
    }

    // =============================================================== KHÁCH ĐẶT ONLINE

    public function giuCho(User $khach, San $san, string $ngay, int $khungGioId, ?int $thoiLuong = null, ?int $batDau = null): DatSan
    {
        if ($khach->biHanChe()) {
            $this->loi('Tài khoản đang bị hạn chế đặt sân: '.$khach->ly_do_han_che.'. Liên hệ chủ sân để được gỡ.');
        }
        if ($ly = $khach->lyDoCamDat()) {
            $this->loi($ly);
        }
        $hoSo = $san->chuSan->hoSoChuSan;
        if ($san->trang_thai !== 'hoat_dong' || ! $hoSo?->conHan()) {
            $this->loi('Sân này đang tạm ngưng nhận đặt.');
        }
        $dungCong = $hoSo->phuong_thuc_tt === 'payos' && $hoSo->payos_client_id && $hoSo->payos_api_key && $hoSo->payos_checksum_key;
        if (! $dungCong && (! $hoSo->ngan_hang_bin || ! $hoSo->so_tai_khoan)) {
            $this->loi('Chủ sân chưa cài đặt tài khoản nhận cọc. Vui lòng gọi '.$hoSo->sdt_lien_he.' để đặt.');
        }

        $ngayC = Carbon::parse($ngay)->startOfDay();
        if ($ngayC->gt(today()->addDays(self::SO_NGAY_DAT_TRUOC))) {
            $this->loi('Chỉ đặt trước tối đa '.self::SO_NGAY_DAT_TRUOC.' ngày.');
        }

        $khung = $this->khungApDung($san, $ngayC, $khungGioId);
        $bd = $batDau ?? $khung->phutBatDau();
        if ($bd % 15 !== 0 || $bd < $khung->phutBatDau() || $bd >= $khung->phutKetThuc()) {
            $this->loi('Giờ bắt đầu phải nằm trong khung '.$khung->nhan().', theo bước 15 phút.');
        }
        if ($ngayC->copy()->addMinutes($bd)->lte(now())) {
            $this->loi('Giờ này đã qua.');
        }

        $this->nhaChoQuaHan();
        $dangGiu = DatSan::where('khach_id', $khach->id)->where('trang_thai', 'cho_thanh_toan')->count();
        if ($dangGiu >= self::SO_DON_CHO_TOI_DA) {
            $this->loi('Bạn đang giữ '.$dangGiu.' khung chưa cọc. Hãy cọc hoặc huỷ bớt trước khi giữ thêm.');
        }

        $phut = $this->kiemThoiLuong($san, $khung, $thoiLuong, $bd);
        $don = $this->ghiDon($san, $ngayC->toDateString(), $bd, $bd + $phut, [
            'khach_id' => $khach->id,
            'khung_gio_id' => $khung->id,
            'gia_chot' => $khung->giaTheoPhut($phut),
            'tien_coc' => $khung->cocTheoPhut($phut, $hoSo->ty_le_coc),
            'nguon' => 'online',
            'trang_thai' => 'cho_thanh_toan',
            'giu_cho_den' => now()->addMinutes($hoSo->phut_giu_cho),
        ]);

        $gd = new GiaoDich;
        $gd->forceFill([
            'loai' => 'coc_dat_san',
            'dat_san_id' => $don->id,
            'chu_san_id' => $san->chu_san_id,
            'nguoi_tra_id' => $khach->id,
            'phuong_thuc' => $dungCong ? 'payos' : 'vietqr',
            'noi_dung_ck' => $don->ma_don,
            'so_tien' => $don->tien_coc,
            'trang_thai' => 'cho_thanh_toan',
        ])->save();

        if ($dungCong) {
            // Cách B: tạo yêu cầu thanh toán trên cổng của CHÍNH chủ sân. Lỗi → huỷ giữ chỗ, trả ô.
            try {
                $kq = $this->quanLyCong->cong('payos')->taoThanhToan($gd, $don->ma_don, $don->giu_cho_den->getTimestamp(), $hoSo, route('dat-san.thanh-toan', $don));
                $gd->forceFill(['ma_cong' => $kq['ma_cong'], 'du_lieu_cong' => $kq])->save();
            } catch (\Throwable $e) {
                report($e);
                $this->huy($don, $khach, 'Lỗi tạo thanh toán qua cổng');
                $this->loi('Cổng thanh toán của sân đang lỗi, vui lòng thử lại sau ít phút hoặc gọi '.$hoSo->sdt_lien_he.'.');
            }
        }
        BaoTin::gui(BaoTin::phiaSan($san->chu_san_id), 'Đơn đặt mới '.$don->ma_don,
            $khach->name.' giữ '.$don->moTaNgan().', chờ cọc '.Tien::chuoi($don->tien_coc).'đ.', route('quan-ly.dat-san.index', ['ngay' => $don->ngay instanceof \DateTimeInterface ? $don->ngay->format('Y-m-d') : substr((string) $don->ngay, 0, 10)]), 'calendar-plus');

        return $don;
    }

    /**
     * Cổng báo tiền đã về (webhook đã kiểm chữ ký). Trả về mã kết quả để ghi nhật ký.
     *  - Sai số tiền → KHÔNG xác nhận (chủ sân xem lại).
     *  - Đơn còn chờ cọc → tự chuyển "Đã đặt".
     *  - Đơn đã bị nhả vì quá hạn mà tiền mới về → đánh dấu chờ hoàn cọc cho khách.
     */
    public function nhanTienTuCong(GiaoDich $gd, int $soTien, string $thamChieu, array $duLieu): string
    {
        if ($gd->loai !== 'coc_dat_san') {
            return app(KeoService::class)->nhanTienCocKeo($gd, $soTien, $thamChieu, $duLieu);
        }

        [$ketQua, $don] = DB::transaction(function () use ($gd, $soTien, $thamChieu, $duLieu) {
            $gd = GiaoDich::lockForUpdate()->findOrFail($gd->id);
            if ($gd->trang_thai === 'thanh_cong') {
                return ['da_xu_ly_truoc', null];
            }
            if ($soTien !== (int) $gd->so_tien) {
                $gd->forceFill(['du_lieu_cong' => array_merge((array) $gd->du_lieu_cong, ['webhook_sai_tien' => $duLieu])])->save();

                return ['sai_so_tien', null];
            }

            $gd->forceFill([
                'trang_thai' => 'thanh_cong', 'thanh_toan_luc' => now(), 'ma_tham_chieu' => $thamChieu,
                'du_lieu_cong' => array_merge((array) $gd->du_lieu_cong, ['webhook' => $duLieu]),
            ])->save();

            $don = DatSan::lockForUpdate()->findOrFail($gd->dat_san_id);
            if ($don->trang_thai === 'cho_thanh_toan') {
                $don->forceFill(['trang_thai' => 'da_dat', 'xac_nhan_coc_luc' => now(), 'xac_nhan_coc_boi_id' => null])->save();

                return ['da_xac_nhan', $don];
            }
            if ($don->trang_thai === 'da_huy' && $don->xu_ly_coc === 'khong_ap_dung') {
                $don->forceFill(['xac_nhan_coc_luc' => now(), 'xu_ly_coc' => 'cho_hoan'])->save();

                return ['tien_ve_sau_khi_huy', null];
            }

            return ['da_xu_ly_truoc', null];
        });

        $don?->khach?->notify(new DatSanThanhCong($don));

        return $ketQua;
    }

    // =============================================================== CHỦ SÂN / NHÂN VIÊN

    /** Đánh dấu đặt tại chỗ (khách gọi điện / đến trực tiếp) → "Đã đặt" ngay. */
    public function datTaiCho(User $nguoiTao, San $san, string $ngay, int $khungGioId, string $ten, string $sdt, bool $daThuCoc, ?string $ghiChu = null, ?int $thoiLuong = null, ?int $batDau = null): DatSan
    {
        $ngayC = Carbon::parse($ngay)->startOfDay();
        $khung = $this->khungApDung($san, $ngayC, $khungGioId);
        $bd = $batDau ?? $khung->phutBatDau();
        $phut = $this->kiemThoiLuong($san, $khung, $thoiLuong, $bd);
        if ($ngayC->copy()->addMinutes($bd + $phut)->lte(now())) {
            $this->loi('Khung giờ này đã kết thúc.');
        }

        $tyLe = $san->chuSan->hoSoChuSan?->ty_le_coc ?? 30;
        $coc = $daThuCoc ? $khung->cocTheoPhut($phut, $tyLe) : 0;

        $don = $this->ghiDon($san, $ngayC->toDateString(), $bd, $bd + $phut, [
            'ten_khach_tai_cho' => $ten,
            'sdt_khach_tai_cho' => $sdt,
            'khung_gio_id' => $khung->id,
            'gia_chot' => $khung->giaTheoPhut($phut),
            'tien_coc' => $coc,
            'nguon' => 'tai_cho',
            'trang_thai' => 'da_dat',
            'nguoi_tao_id' => $nguoiTao->id,
            'xac_nhan_coc_boi_id' => $daThuCoc ? $nguoiTao->id : null,
            'xac_nhan_coc_luc' => $daThuCoc ? now() : null,
            'ghi_chu' => $ghiChu,
        ]);

        if ($coc > 0) {
            $gd = new GiaoDich;
            $gd->forceFill([
                'loai' => 'coc_dat_san', 'dat_san_id' => $don->id, 'chu_san_id' => $san->chu_san_id,
                'phuong_thuc' => 'tien_mat', 'noi_dung_ck' => $don->ma_don, 'so_tien' => $coc,
                'trang_thai' => 'thanh_cong', 'thanh_toan_luc' => now(), 'xac_nhan_boi_id' => $nguoiTao->id,
            ])->save();
        }

        return $don;
    }

    /** Chủ sân / nhân viên đã thấy tiền về app ngân hàng → bấm xác nhận. */
    public function xacNhanCoc(DatSan $don, User $nguoi, bool $ngoai = false): DatSan
    {
        $don = DB::transaction(function () use ($don, $nguoi, $ngoai) {
            $don = DatSan::lockForUpdate()->findOrFail($don->id); // khoá dòng: 2 nhân viên bấm cùng lúc không bị ghi đôi
            if ($don->trang_thai !== 'cho_thanh_toan') {
                $this->loi($don->trang_thai === 'da_dat' ? 'Đơn này đã được xác nhận rồi.' : 'Đơn đã huỷ/hết hạn giữ chỗ. Hãy đặt lại cho khách.');
            }
            if (\App\Models\Keo::where('dat_san_id', $don->id)->where('trang_thai', 'cho_coc')->exists()) {
                $this->loi('Đơn của kèo: xác nhận cọc từng đội ở mục "Cọc kèo chờ xác nhận".');
            }

            $don->forceFill(['trang_thai' => 'da_dat', 'xac_nhan_coc_boi_id' => $nguoi->id, 'xac_nhan_coc_luc' => now()])->save();
            GiaoDich::where('dat_san_id', $don->id)->where('trang_thai', 'cho_thanh_toan')
                ->update(['trang_thai' => 'thanh_cong', 'thanh_toan_luc' => now(), 'xac_nhan_boi_id' => $nguoi->id]
                    + ($ngoai ? ['phuong_thuc' => 'tien_mat'] : []));

            return $don;
        });

        $don->khach?->notify(new DatSanThanhCong($don));
        BaoTin::gui($don->khach, 'Đã xác nhận cọc '.$don->ma_don, 'Chủ sân đã nhận cọc. Lịch '.$don->moTaNgan().' đã chắc chắn.', route('dat-san.thanh-toan', $don), 'check-circle');

        return $don;
    }

    /** Khách báo đã chuyển nhưng tiền chưa về → giữ thêm N phút. */
    public function giaHanGiuCho(DatSan $don, int $phut = 15): DatSan
    {
        if ($don->trang_thai !== 'cho_thanh_toan') {
            $this->loi('Chỉ gia hạn được đơn đang chờ cọc.');
        }
        $tu = $don->giu_cho_den && $don->giu_cho_den->isFuture() ? $don->giu_cho_den : now();
        $don->forceFill(['giu_cho_den' => $tu->copy()->addMinutes($phut)])->save();

        return $don;
    }

    /**
     * Huỷ đơn. Chính sách cọc:
     *  - Đơn chưa cọc / đặt tại chỗ: không có gì để hoàn.
     *  - Chủ sân huỷ: phải hoàn cọc.
     *  - Khách huỷ trước giờ đá >= gio_huy_hoan_coc (mặc định 24h): được hoàn; sau đó: mất cọc.
     */
    public function huy(DatSan $don, User $nguoi, ?string $lyDo = null): DatSan
    {
        $don = DB::transaction(function () use ($don, $nguoi, $lyDo) {
            $don = DatSan::lockForUpdate()->findOrFail($don->id);
            if ($don->trang_thai === 'da_huy') {
                $this->loi('Đơn đã huỷ trước đó.');
            }

            $laKhach = $don->khach_id !== null && (int) $don->khach_id === $nguoi->id;
            if ($laKhach && $don->batDauLuc()->lte(now())) {
                $this->loi('Đã tới giờ đá, không huỷ được nữa.');
            }

            $xuLy = 'khong_ap_dung';
            if ($don->trang_thai === 'da_dat' && $don->tien_coc > 0 && $don->xac_nhan_coc_luc) {
                if (! $laKhach) {
                    $xuLy = 'cho_hoan';
                } else {
                    $gioHuy = $don->san->chuSan->hoSoChuSan?->gio_huy_hoan_coc ?? 24;
                    $xuLy = now()->diffInMinutes($don->batDauLuc(), false) >= $gioHuy * 60 ? 'cho_hoan' : 'mat_coc';
                }
            }

            $don->forceFill([
                'trang_thai' => 'da_huy', 'huy_luc' => now(), 'huy_boi_id' => $nguoi->id,
                'ly_do_huy' => $lyDo, 'xu_ly_coc' => $xuLy,
            ])->save();

            DB::table('o_lich')->where('dat_san_id', $don->id)->delete(); // nhả ô → người khác đặt được ngay
            GiaoDich::where('dat_san_id', $don->id)->where('trang_thai', 'cho_thanh_toan')->update(['trang_thai' => 'da_huy']);

            // Lịch có kèo → huỷ kèo, ai đã cọc kèo thì chờ chủ sân hoàn
            if ($keo = \App\Models\Keo::where('dat_san_id', $don->id)->first()) {
                app(KeoService::class)->huyKeoBoiSan($keo, 'Lịch sân bị huỷ');
            }

            return $don;
        });

        $coc = ['cho_hoan' => ' Tiền cọc sẽ được chủ sân hoàn lại.', 'mat_coc' => ' Huỷ sát giờ nên mất cọc.'][$don->xu_ly_coc] ?? '';
        if ($don->khach_id && (int) $don->khach_id !== $nguoi->id) {
            BaoTin::gui($don->khach_id, 'Đơn '.$don->ma_don.' bị huỷ', $don->moTaNgan().($lyDo ? ' — '.$lyDo : '').'.'.$coc, route('khach.thanh-toan'), 'x-circle', true);
        } elseif ($don->khach_id) {
            BaoTin::gui(BaoTin::phiaSan($don->san->chu_san_id), 'Khách huỷ đơn '.$don->ma_don, $don->tenNguoiDat().' huỷ '.$don->moTaNgan().'.'.$coc, route('quan-ly.dat-san.index'), 'x-circle');
        }

        return $don;
    }

    // =============================================================== CHỐNG BÙNG / BÁO CHUYỂN KHOẢN

    public const PHUT_GIU_SAU_BAO_CHUYEN = 120;
    public const PHAT_BAO_SAI = 5;
    public const PHAT_KHONG_TRA_DU = 20;
    public const NGAY_CAM = 30;

    /** Khách bấm "Tôi đã chuyển khoản": mỗi đơn 1 lần, giữ chỗ thêm 2 tiếng, báo email chủ sân + nhân viên. */
    public function baoDaChuyen(DatSan $don, User $khach): DatSan
    {
        $don = DB::transaction(function () use ($don, $khach) {
            $don = DatSan::lockForUpdate()->findOrFail($don->id);
            if ((int) $don->khach_id !== $khach->id || $don->trang_thai !== 'cho_thanh_toan') {
                $this->loi('Đơn này không còn chờ cọc.');
            }
            if ($don->bao_da_chuyen_luc) {
                $this->loi('Bạn đã báo chuyển khoản cho đơn này rồi. Chủ sân đang kiểm tra.');
            }
            $den = now()->addMinutes(self::PHUT_GIU_SAU_BAO_CHUYEN);
            $don->forceFill(['bao_da_chuyen_luc' => now(), 'giu_cho_den' => $don->giu_cho_den?->gt($den) ? $don->giu_cho_den : $den])->save();

            return $don;
        });

        $san = $don->san;
        User::where(fn ($q) => $q->where('id', $san->chu_san_id)->orWhere(fn ($q) => $q->where('chu_san_id', $san->chu_san_id)->where('vai_tro', User::NHAN_VIEN)))
            ->where('trang_thai', 'hoat_dong')->get()
            ->each(fn (User $u) => $u->notify(new \App\Notifications\KhachBaoDaChuyen($don)));
        BaoTin::gui(BaoTin::phiaSan($don->san->chu_san_id), 'Khách báo đã chuyển '.$don->ma_don,
            $don->tenNguoiDat().' báo đã chuyển '.Tien::chuoi($don->tien_coc).'đ cho '.$don->moTaNgan().'. Kiểm tra app ngân hàng.', route('quan-ly.dat-san.index'), 'cash-coin');

        return $don;
    }

    /** Chủ sân / nhân viên kiểm tra app ngân hàng: không thấy tiền → huỷ đơn, phạt khách báo sai. */
    public function khongThayTien(DatSan $don, User $nguoi): DatSan
    {
        if ($don->trang_thai !== 'cho_thanh_toan' || ! $don->bao_da_chuyen_luc || ! $don->khach_id) {
            $this->loi('Chỉ dùng cho đơn khách đã báo chuyển khoản mà chưa xác nhận.');
        }
        $don = $this->huy($don, $nguoi, 'Báo chuyển khoản sai (chủ sân không thấy tiền)');

        DB::transaction(function () use ($don) {
            $k = User::lockForUpdate()->findOrFail($don->khach_id);
            $k->forceFill(['diem_uy_tin' => max(0, $k->diem_uy_tin - self::PHAT_BAO_SAI)])->save();
            $this->ghiUyTin($k, -self::PHAT_BAO_SAI, 'bao_chuyen_sai', 'Đơn '.$don->ma_don);
            $soLan = DB::table('lich_su_uy_tin')->where('user_id', $k->id)->where('ly_do', 'bao_chuyen_sai')
                ->where('created_at', '>=', now()->subDays(self::NGAY_CAM))->count();
            if ($soLan >= 2) {
                $k->forceFill(['cam_dat_den' => now()->addDays(self::NGAY_CAM), 'ly_do_cam_dat' => 'báo chuyển khoản sai 2 lần trong 30 ngày'])->save();
            }
        });
        BaoTin::gui($don->khach_id, 'Bị trừ '.self::PHAT_BAO_SAI.' uy tín', 'Chủ sân không thấy tiền cọc đơn '.$don->ma_don.' bạn báo đã chuyển. Đơn đã huỷ.', route('khach.thanh-toan'), 'exclamation-triangle');

        return $don;
    }

    /**
     * Chủ sân: khách đá xong không trả đủ tiền sân.
     * Lần 1: trừ 20 uy tín + cấm đặt sân & cáp kèo 30 ngày. Lần 2: khoá hẳn tài khoản — email & SĐT
     * vẫn nằm trong bảng users (UNIQUE) nên không đăng ký lại được bằng chúng.
     */
    public function khongThanhToanDu(DatSan $don, User $chuSan): User
    {
        if ($don->trang_thai !== 'da_dat' || ! $don->khach_id || $don->khong_tra_du_luc || $don->batDauLuc()->isFuture()) {
            $this->loi('Chỉ báo với đơn online đã đá (hoặc đang đá) và chưa báo trước đó.');
        }

        return DB::transaction(function () use ($don, $chuSan) {
            $don->forceFill(['khong_tra_du_luc' => now()])->save();
            $k = User::lockForUpdate()->findOrFail($don->khach_id);
            $lanTruoc = DatSan::where('khach_id', $k->id)->whereNotNull('khong_tra_du_luc')->where('id', '!=', $don->id)->exists();

            $k->forceFill(['diem_uy_tin' => max(0, $k->diem_uy_tin - self::PHAT_KHONG_TRA_DU)]);
            if ($lanTruoc) {
                $k->forceFill(['trang_thai' => 'bi_khoa', 'ly_do_khoa' => 'Không thanh toán đủ tiền sân lần 2 (đơn '.$don->ma_don.')']);
            } else {
                $k->forceFill(['cam_dat_den' => now()->addDays(self::NGAY_CAM), 'ly_do_cam_dat' => 'không thanh toán đủ tiền sân đơn '.$don->ma_don]);
            }
            $k->save();
            $this->ghiUyTin($k, -self::PHAT_KHONG_TRA_DU, 'khong_tra_du', 'Đơn '.$don->ma_don.' — báo bởi '.$chuSan->name);
            BaoTin::gui($k, 'Bị trừ '.self::PHAT_KHONG_TRA_DU.' uy tín', 'Chủ sân báo bạn không thanh toán đủ tiền đơn '.$don->ma_don.'. '.($k->trang_thai === 'bi_khoa' ? 'Tài khoản đã bị khoá.' : 'Bạn bị cấm đặt sân và cáp kèo '.self::NGAY_CAM.' ngày.'), null, 'exclamation-triangle', true);

            return $k;
        });
    }

    /** Scheduler: đơn khách báo đã chuyển còn ≤ 30 phút giữ chỗ mà chưa ai xử lý → nhắc lại chủ sân. */
    public function nhacBaoChuyen(): int
    {
        $so = 0;
        DatSan::with('san')->where('trang_thai', 'cho_thanh_toan')->whereNotNull('bao_da_chuyen_luc')->where('da_nhac_bao_chuyen', false)
            ->where('giu_cho_den', '<=', now()->addMinutes(30))->get()
            ->each(function (DatSan $don) use (&$so) {
                $don->forceFill(['da_nhac_bao_chuyen' => true])->save();
                User::find($don->san->chu_san_id)?->notify(new \App\Notifications\KhachBaoDaChuyen($don, true));
                $so++;
            });

        return $so;
    }

    private function ghiUyTin(User $u, int $thayDoi, string $lyDo, string $ghiChu): void
    {
        DB::table('lich_su_uy_tin')->insert(['user_id' => $u->id, 'thay_doi' => $thayDoi, 'ly_do' => $lyDo, 'ghi_chu' => $ghiChu, 'created_at' => now()]);
    }

    public function daThuTienSan(DatSan $don, User $nguoi, int $soTien, string $phuongThuc): DatSan
    {
        if ($don->trang_thai !== 'da_dat' || $don->da_thu_tien_luc) {
            $this->loi('Đơn này chưa đặt chắc hoặc đã ghi nhận thu tiền rồi.');
        }
        if (! in_array($phuongThuc, ['tien_mat', 'online'], true)) {
            $this->loi('Chọn hình thức thu: tiền mặt hoặc online.');
        }
        if ($soTien < 0 || $soTien > max(1, $don->gia_chot) * 3) {
            $this->loi('Số tiền thu không hợp lệ.');
        }
        $don->forceFill([
            'tien_thu_tai_san' => $soTien, 'da_thu_tien_luc' => now(),
            'thu_tien_boi_id' => $nguoi->id, 'phuong_thuc_thu_tai_san' => $phuongThuc,
        ])->save();

        return $don;
    }

    /** Chủ sân đã tự chuyển trả cọc cho khách → bấm xác nhận để lưu vết. */
    public function xacNhanDaHoan(DatSan $don): DatSan
    {
        if ($don->xu_ly_coc !== 'cho_hoan') {
            $this->loi('Đơn này không ở trạng thái chờ hoàn cọc.');
        }
        $don->forceFill(['xu_ly_coc' => 'da_hoan', 'da_hoan_luc' => now()])->save();
        BaoTin::gui($don->khach_id, 'Đã hoàn cọc '.$don->ma_don, 'Chủ sân đã chuyển trả '.Tien::chuoi($don->tien_coc).'đ tiền cọc. Kiểm tra tài khoản của bạn.', route('khach.thanh-toan'), 'arrow-counterclockwise', true);

        return $don;
    }

    /** Nhả mọi ô quá hạn giữ chỗ mà chưa cọc. Scheduler gọi mỗi phút; trang lịch cũng gọi trước khi hiển thị. */
    public function nhaChoQuaHan(): int
    {
        $so = 0;
        DatSan::where('trang_thai', 'cho_thanh_toan')
            ->where('giu_cho_den', '<', now())
            ->pluck('id')
            ->each(function ($id) use (&$so) {
                DB::transaction(function () use ($id, &$so) {
                    $don = DatSan::lockForUpdate()->find($id);
                    if (! $don || $don->trang_thai !== 'cho_thanh_toan' || $don->giu_cho_den?->isFuture()) {
                        return; // vừa được xác nhận / gia hạn
                    }
                    $don->forceFill(['trang_thai' => 'da_huy', 'huy_luc' => now(), 'ly_do_huy' => 'Hết thời gian giữ chỗ'])->save();
                    DB::table('o_lich')->where('dat_san_id', $id)->delete();
                    GiaoDich::where('dat_san_id', $id)->where('trang_thai', 'cho_thanh_toan')->update(['trang_thai' => 'het_han']);
                    if ($don->khach_id && ! \App\Models\Keo::where('dat_san_id', $id)->exists()) {
                        BaoTin::gui($don->khach_id, 'Hết giờ giữ chỗ '.$don->ma_don, 'Chưa nhận được cọc nên chỗ đã nhả cho người khác. Muốn đá thì đặt lại nhé.', route('khach.thanh-toan'), 'hourglass-bottom');
                    }
                    $so++;
                });
            });

        return $so;
    }

    // =============================================================== LÕI

    /**
     * Ghi đơn + chiếm ô lịch trong MỘT transaction.
     * Seeder dùng trực tiếp hàm này để tạo dữ liệu quá khứ.
     */
    public function ghiDon(San $san, string $ngay, int $bd, int $kt, array $cot): DatSan
    {
        try {
            $don = DB::transaction(function () use ($san, $ngay, $bd, $kt, $cot) {
                $don = new DatSan;
                $don->forceFill($cot + [
                    'ma_don' => 'T'.Str::upper(Str::random(15)), // tạm, đổi ngay sau khi có id
                    'san_id' => $san->id,
                    'ngay' => $ngay,
                    'gio_bat_dau' => ThoiGian::csdl($bd),
                    'gio_ket_thuc' => ThoiGian::csdl($kt),
                ])->save();
                $don->forceFill(['ma_don' => sprintf('DS%06d', $don->id)])->save();

                $o = [];
                foreach (array_merge([$san->id], $san->idsSanCon()) as $sanId) {
                    for ($p = $bd; $p < $kt; $p += ThoiGian::BUOC_PHUT) {
                        $o[] = [
                            'san_id' => $sanId, 'ngay' => $ngay, 'o' => intdiv($p, ThoiGian::BUOC_PHUT),
                            'dat_san_id' => $don->id, 'do_san_lien_quan' => $sanId !== $san->id,
                        ];
                    }
                }
                DB::table('o_lich')->insert($o);

                return $don;
            });
        } catch (UniqueConstraintViolationException) {
            $this->loi('Khung giờ này vừa có người đặt (hoặc sân ghép liên quan đang bận). Vui lòng chọn giờ khác.');
        }
        // Đặt sân thường → kèo "chưa đặt sân" trùng khung giờ tự huỷ. Đơn do kèo tự chốt thì KeoService tự quét (trừ chính kèo đó).
        if (! str_starts_with((string) ($cot['ghi_chu'] ?? ''), 'Kèo #')) {
            app(KeoService::class)->quetKeoTruocMatSan();
        }

        return $don;
    }

    /** Thời lượng đá tính từ giờ bắt đầu khung: bội số 15', 30'–240', không quá giờ đóng cửa. Trùng giờ đã có người do ghiDon chặn. */
    public function kiemThoiLuong(San $san, KhungGio $khung, ?int $thoiLuong, ?int $batDau = null): int
    {
        $phut = $thoiLuong ?: $khung->soPhut();
        if ($phut % 15 !== 0 || $phut < 30 || $phut > 240) {
            $this->loi('Thời lượng phải từ 30 phút đến 4 tiếng, theo bước 15 phút.');
        }
        if (($batDau ?? $khung->phutBatDau()) + $phut > \App\Support\ThoiGian::phut($san->gio_dong_cua)) {
            $this->loi('Đá '.$phut.' phút sẽ quá giờ đóng cửa của sân.');
        }

        return $phut;
    }

    private function khungApDung(San $san, Carbon $ngay, int $khungGioId): KhungGio
    {
        $khung = $this->bangGia->khungGioTrongNgay($san, $ngay)->firstWhere('id', $khungGioId);
        if (! $khung) {
            $this->loi('Khung giờ không áp dụng cho ngày này.');
        }

        return $khung;
    }

    /** @return never */
    /** Đặt cố định theo tuần cho khách tại chỗ, từ ngày đã chọn đến hết năm. Buổi trùng hoặc đã qua thì bỏ qua và báo lại. */
    public function taoChuoiCoDinh(User $nguoi, San $san, int $thu, int $batDau, int $ketThuc, string $ten, string $sdt, string $tuNgay, ?string $denNgay = null): array
    {
        $bd = Carbon::parse($tuNgay)->startOfDay();
        if ($bd->lt(today())) {
            $this->loi('Ngày bắt đầu không được ở quá khứ.');
        }
        if ($batDau % ThoiGian::BUOC_PHUT !== 0 || $ketThuc % ThoiGian::BUOC_PHUT !== 0) {
            $this->loi('Giờ bắt đầu và giờ kết thúc phải theo bước 15 phút.');
        }
        if ($ketThuc <= $batDau) {
            $this->loi('Giờ kết thúc phải sau giờ bắt đầu.');
        }
        $cuoi = $denNgay ? Carbon::parse($denNgay)->endOfDay() : $bd->copy()->endOfYear();
        $thoiLuong = $ketThuc - $batDau;

        $d0 = $bd->copy();
        while ($d0->dayOfWeek !== $thu) {
            $d0->addDay();
        }
        $khung0 = $this->khungTaiGio($san, $d0, $batDau);
        if (! $khung0) {
            $this->loi('Giờ bắt đầu không nằm trong khung giá nào của ngày '.$d0->format('d/m/Y').'.');
        }

        $chuoi = new ChuoiCoDinh;
        $chuoi->forceFill(['san_id' => $san->id, 'thu' => $thu, 'khung_gio_id' => $khung0->id, 'ten' => $ten,
            'sdt' => $sdt, 'gio_bat_dau' => ThoiGian::csdl($batDau), 'gio_ket_thuc' => ThoiGian::csdl($ketThuc),
            'den_ngay' => $cuoi->toDateString(), 'thoi_luong' => $thoiLuong, 'nguoi_tao_id' => $nguoi->id])->save();

        $so = 0;
        $boQua = [];
        for ($d = $d0->copy(); $d->lte($cuoi); $d->addWeek()) {
            try {
                $khung = $this->khungTaiGio($san, $d, $batDau);
                if (! $khung) {
                    $this->loi('Không có khung giá.');
                }
                $don = $this->datTaiCho($nguoi, $san, $d->toDateString(), $khung->id, $ten, $sdt, false, 'Cố định hằng tuần', $thoiLuong, $batDau);
                $don->forceFill(['chuoi_co_dinh_id' => $chuoi->id])->save();
                $so++;
            } catch (ValidationException) {
                $boQua[] = $d->format('d/m/Y');
            }
        }

        return ['chuoi' => $chuoi, 'so' => $so, 'bo_qua' => $boQua];
    }

    /** Khung giá áp dụng cho ngày đó mà chứa giờ bắt đầu, hoặc null nếu giờ đó nằm ngoài mọi khung. */
    private function khungTaiGio(San $san, Carbon $ngay, int $batDau): ?KhungGio
    {
        return $this->bangGia->khungGioTrongNgay($san, $ngay)
            ->first(fn (KhungGio $k) => $k->phutBatDau() <= $batDau && $batDau < $k->phutKetThuc());
    }

    /** Bỏ các buổi còn hiệu lực của chuỗi trong khoảng ngày. Buổi đã thu tiền sân thì giữ lại để không lệch thống kê. */
    public function huyChuoiTrongKhoang(ChuoiCoDinh $chuoi, User $nguoi, string $tu, string $den): array
    {
        $huy = 0;
        $giu = 0;
        $chuoi->dat()->where('trang_thai', '!=', 'da_huy')->whereBetween('ngay', [$tu, $den])->orderBy('ngay')->get()
            ->each(function (DatSan $don) use ($nguoi, &$huy, &$giu) {
                if ($don->da_thu_tien_luc) {
                    $giu++;

                    return;
                }
                $this->huy($don, $nguoi, 'Bỏ lịch cố định');
                $huy++;
            });

        return ['huy' => $huy, 'giu' => $giu];
    }

    private function loi(string $thongBao): never
    {
        throw ValidationException::withMessages(['dat_san' => $thongBao]);
    }
}
