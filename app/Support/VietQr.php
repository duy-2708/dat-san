<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Sinh mã VietQR (chuẩn EMVCo của NAPAS) ngay trên server — không gọi dịch vụ ngoài.
 * Quét được bằng app của mọi ngân hàng và ví hỗ trợ VietQR (Vietcombank, TPBank, MoMo, ZaloPay...).
 * Mã "động": có sẵn số tiền + nội dung chuyển khoản, khách không phải gõ tay.
 */
class VietQr
{
    /** Mã BIN NAPAS của các ngân hàng phổ biến. */
    public const NGAN_HANG = [
        '970436' => 'Vietcombank',
        '970415' => 'VietinBank',
        '970418' => 'BIDV',
        '970405' => 'Agribank',
        '970407' => 'Techcombank',
        '970422' => 'MB Bank',
        '970416' => 'ACB',
        '970432' => 'VPBank',
        '970423' => 'TPBank',
        '970403' => 'Sacombank',
        '970437' => 'HDBank',
        '970441' => 'VIB',
        '970443' => 'SHB',
        '970440' => 'SeABank',
        '970448' => 'OCB',
        '970426' => 'MSB',
        '970431' => 'Eximbank',
        '970449' => 'LPBank',
        '970428' => 'Nam A Bank',
        '970454' => 'BVBank (Bản Việt)',
        '970409' => 'Bac A Bank',
        '970412' => 'PVcomBank',
        '970419' => 'NCB',
        '970427' => 'VietABank',
        '970425' => 'ABBANK',
        '970452' => 'KienlongBank',
        '970400' => 'Saigonbank',
        '970438' => 'BaoViet Bank',
    ];

    /** Chuỗi dữ liệu VietQR. Nội dung chỉ nên là chữ không dấu + số, tối đa 25 ký tự. */
    public static function chuoi(string $bin, string $soTaiKhoan, int $soTien, string $noiDung): string
    {
        $noiDung = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $noiDung), 0, 25);

        $nguoiNhan = self::truong('00', $bin).self::truong('01', $soTaiKhoan);
        $thongTinTk = self::truong('00', 'A000000727')        // GUID của NAPAS
            .self::truong('01', $nguoiNhan)
            .self::truong('02', 'QRIBFTTA');                    // chuyển tiền nhanh 24/7 tới tài khoản

        $du = self::truong('00', '01')          // phiên bản
            .self::truong('01', '12')           // 12 = QR động (dùng 1 lần, có số tiền)
            .self::truong('38', $thongTinTk)
            .self::truong('53', '704')          // VND
            .self::truong('54', (string) $soTien)
            .self::truong('58', 'VN')
            .self::truong('62', self::truong('08', $noiDung))
            .'6304';

        return $du.self::crc16($du);
    }

    /** Ảnh QR dạng SVG (nhúng thẳng vào HTML). */
    public static function svg(string $chuoi, int $kichThuoc = 280): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($kichThuoc, 1), new SvgImageBackEnd())))->writeString($chuoi);

        return substr($svg, strpos($svg, '<svg')); // bỏ dòng khai báo xml ở đầu
    }

    /** "TPBank" / "tp bank" / "970423" → "970423"; không nhận ra thì trả nguyên chuỗi (để báo lỗi). */
    public static function timBin(?string $nhap): ?string
    {
        $n = trim((string) $nhap);
        if ($n === '' || isset(self::NGAN_HANG[$n])) {
            return $n === '' ? null : $n;
        }
        $rut = fn ($s) => preg_replace('/[^a-z0-9]/', '', \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($s)));
        foreach (self::NGAN_HANG as $bin => $ten) {
            if ($rut($ten) === $rut($n)) {
                return (string) $bin;
            }
        }

        return $n;
    }

    public static function tenNganHang(?string $bin): string
    {
        return self::NGAN_HANG[$bin] ?? (string) $bin;
    }

    /** "Nguyễn Văn A" → "NGUYEN VAN A" (cách ngân hàng in tên chủ tài khoản). */
    public static function tenKhongDau(string $ten): string
    {
        $ten = str_replace(['đ', 'Đ'], ['d', 'D'], $ten);
        $ten = \Normalizer::normalize($ten, \Normalizer::FORM_D);
        $ten = preg_replace('/\p{Mn}/u', '', $ten);

        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', $ten)));
    }

    private static function truong(string $id, string $giaTri): string
    {
        return $id.str_pad((string) strlen($giaTri), 2, '0', STR_PAD_LEFT).$giaTri;
    }

    /** CRC-16/CCITT-FALSE (đa thức 0x1021, khởi tạo 0xFFFF) theo chuẩn EMVCo. */
    public static function crc16(string $du): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($du); $i < $n; $i++) {
            $crc ^= ord($du[$i]) << 8;
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
