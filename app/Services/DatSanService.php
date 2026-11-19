<?php

namespace App\Services;

use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\KhungGio;
use App\Models\San;
use App\Models\User;
use App\Notifications\DatSanThanhCong;
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

    public function __construct(private BangGia $bangGia)
    {
    }

    // =============================================================== KHÁCH ĐẶT ONLINE

    public function giuCho(User $khach, San $san, string $ngay, int $khungGioId): DatSan
    {
        $hoSo = $san->chuSan->hoSoChuSan;
        if ($san->trang_thai !== 'hoat_dong' || ! $hoSo?->conHan()) {
            $this->loi('Sân này đang tạm ngưng nhận đặt.');
        }
        if (! $hoSo->ngan_hang_bin || ! $hoSo->so_tai_khoan) {
            $this->loi('Chủ sân chưa cài đặt tài khoản nhận cọc. Vui lòng gọi '.$hoSo->sdt_lien_he.' để đặt.');
        }

        $ngayC = Carbon::parse($ngay)->startOfDay();
        if ($ngayC->gt(today()->addDays(self::SO_NGAY_DAT_TRUOC))) {
            $this->loi('Chỉ đặt trước tối đa '.self::SO_NGAY_DAT_TRUOC.' ngày.');
        }

        $khung = $this->khungApDung($san, $ngayC, $khungGioId);
        if ($ngayC->copy()->addMinutes($khung->phutBatDau())->lte(now())) {
            $this->loi('Khung giờ này đã qua.');
        }

        $this->nhaChoQuaHan();
        $dangGiu = DatSan::where('khach_id', $khach->id)->where('trang_thai', 'cho_thanh_toan')->count();
        if ($dangGiu >= self::SO_DON_CHO_TOI_DA) {
            $this->loi('Bạn đang giữ '.$dangGiu.' khung chưa cọc. Hãy cọc hoặc huỷ bớt trước khi giữ thêm.');
        }

        $don = $this->ghiDon($san, $ngayC->toDateString(), $khung->phutBatDau(), $khung->phutKetThuc(), [
            'khach_id' => $khach->id,
            'khung_gio_id' => $khung->id,
            'gia_chot' => $khung->gia,
            'tien_coc' => $khung->tienCoc($hoSo->ty_le_coc),
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
            'phuong_thuc' => 'vietqr',
            'noi_dung_ck' => $don->ma_don,
            'so_tien' => $don->tien_coc,
            'trang_thai' => 'cho_thanh_toan',
        ])->save();

        return $don;
    }

    // =============================================================== CHỦ SÂN / NHÂN VIÊN

    /** Đánh dấu đặt tại chỗ (khách gọi điện / đến trực tiếp) → "Đã đặt" ngay. */
    public function datTaiCho(User $nguoiTao, San $san, string $ngay, int $khungGioId, string $ten, string $sdt, bool $daThuCoc, ?string $ghiChu = null): DatSan
    {
        $ngayC = Carbon::parse($ngay)->startOfDay();
        $khung = $this->khungApDung($san, $ngayC, $khungGioId);
        if ($ngayC->copy()->addMinutes($khung->phutKetThuc())->lte(now())) {
            $this->loi('Khung giờ này đã kết thúc.');
        }

        $tyLe = $san->chuSan->hoSoChuSan?->ty_le_coc ?? 30;
        $coc = $daThuCoc ? $khung->tienCoc($tyLe) : 0;

        $don = $this->ghiDon($san, $ngayC->toDateString(), $khung->phutBatDau(), $khung->phutKetThuc(), [
            'ten_khach_tai_cho' => $ten,
            'sdt_khach_tai_cho' => $sdt,
            'khung_gio_id' => $khung->id,
            'gia_chot' => $khung->gia,
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
    public function xacNhanCoc(DatSan $don, User $nguoi): DatSan
    {
        $don = DB::transaction(function () use ($don, $nguoi) {
            $don = DatSan::lockForUpdate()->findOrFail($don->id); // khoá dòng: 2 nhân viên bấm cùng lúc không bị ghi đôi
            if ($don->trang_thai !== 'cho_thanh_toan') {
                $this->loi($don->trang_thai === 'da_dat' ? 'Đơn này đã được xác nhận rồi.' : 'Đơn đã huỷ/hết hạn giữ chỗ. Hãy đặt lại cho khách.');
            }

            $don->forceFill(['trang_thai' => 'da_dat', 'xac_nhan_coc_boi_id' => $nguoi->id, 'xac_nhan_coc_luc' => now()])->save();
            GiaoDich::where('dat_san_id', $don->id)->where('trang_thai', 'cho_thanh_toan')
                ->update(['trang_thai' => 'thanh_cong', 'thanh_toan_luc' => now(), 'xac_nhan_boi_id' => $nguoi->id]);

            return $don;
        });

        $don->khach?->notify(new DatSanThanhCong($don));

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
        return DB::transaction(function () use ($don, $nguoi, $lyDo) {
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

            return $don;
        });
    }

    /** Chủ sân đã tự chuyển trả cọc cho khách → bấm xác nhận để lưu vết. */
    public function xacNhanDaHoan(DatSan $don): DatSan
    {
        if ($don->xu_ly_coc !== 'cho_hoan') {
            $this->loi('Đơn này không ở trạng thái chờ hoàn cọc.');
        }
        $don->forceFill(['xu_ly_coc' => 'da_hoan', 'da_hoan_luc' => now()])->save();

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
            return DB::transaction(function () use ($san, $ngay, $bd, $kt, $cot) {
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
    private function loi(string $thongBao): never
    {
        throw ValidationException::withMessages(['dat_san' => $thongBao]);
    }
}
