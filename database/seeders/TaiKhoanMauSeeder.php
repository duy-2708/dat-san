<?php

namespace Database\Seeders;

use App\Models\GoiThueBao;
use App\Models\HoSoChuSan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tài khoản mẫu — mật khẩu chung: 12345678
 *   admin@datsan.test
 *   chusan1@datsan.test (Sân Phú Nhuận, gói Tiêu chuẩn)  → nhanvien1@datsan.test
 *   chusan2@datsan.test (Sân Hải Châu, gói Cơ bản)       → nhanvien2@datsan.test
 *   chusan3@datsan.test (ĐÃ HẾT HẠN — để thử khoá ở giai đoạn 8)
 *   khach1..khach6@datsan.test (khach6 bị khoá)
 */
class TaiKhoanMauSeeder extends Seeder
{
    public function run(): void
    {
        $matKhau = Hash::make('12345678');

        $tao = function (string $email, string $ten, string $sdt, string $vaiTro, ?int $chuSanId = null, string $trangThai = 'hoat_dong') use ($matKhau): User {
            $u = User::firstOrNew(['email' => $email]);
            $u->fill(['name' => $ten, 'so_dien_thoai' => $sdt]);
            $u->password = $matKhau;
            $u->vai_tro = $vaiTro;
            $u->chu_san_id = $chuSanId;
            $u->trang_thai = $trangThai;
            $u->email_verified_at = now();
            $u->save();

            return $u;
        };

        $tao('admin@datsan.test', 'Quản trị hệ thống', '0900000001', User::ADMIN);

        $goi = GoiThueBao::pluck('id', 'ten');
        $chuSan = [
            ['chusan1@datsan.test', 'Nguyễn Văn Chủ', '0900000011', 'Sân bóng Phú Nhuận', 'Tiêu chuẩn', 60, 'nhanvien1@datsan.test', '0900000021'],
            ['chusan2@datsan.test', 'Trần Thị Sân', '0900000012', 'Sân bóng Hải Châu', 'Cơ bản', 20, 'nhanvien2@datsan.test', '0900000022'],
            ['chusan3@datsan.test', 'Lê Hết Hạn', '0900000013', 'Sân bóng Ninh Kiều', 'Cơ bản', -3, null, null],
        ];

        foreach ($chuSan as [$email, $ten, $sdt, $coSo, $tenGoi, $soNgay, $emailNv, $sdtNv]) {
            $u = $tao($email, $ten, $sdt, User::CHU_SAN);

            $hoSo = HoSoChuSan::firstOrNew(['user_id' => $u->id]);
            $hoSo->fill(['ten_co_so' => $coSo, 'sdt_lien_he' => $sdt,
                'ngan_hang_bin' => '970436', 'so_tai_khoan' => '0123456789', 'ten_chu_tai_khoan' => mb_strtoupper($ten)]);
            $hoSo->user_id = $u->id;
            $hoSo->goi_thue_bao_id = $goi[$tenGoi] ?? null;
            $hoSo->het_han_luc = now()->addDays($soNgay);
            $hoSo->save();

            if ($emailNv) {
                $tao($emailNv, 'Nhân viên của '.$ten, $sdtNv, User::NHAN_VIEN, $u->id);
            }
        }

        $khach = ['Phạm Minh Tiền Đạo', 'Võ Hậu Vệ', 'Đặng Thủ Môn', 'Bùi Tiền Vệ', 'Hoàng Cánh Trái', 'Ngô Bị Khoá'];
        foreach ($khach as $i => $ten) {
            $tao('khach'.($i + 1).'@datsan.test', $ten, '09100000'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                User::KHACH, null, $i === 5 ? 'bi_khoa' : 'hoat_dong');
        }
    }
}
