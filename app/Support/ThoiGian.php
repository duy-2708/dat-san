<?php

namespace App\Support;

/**
 * Làm việc với giờ trong ngày dưới dạng SỐ PHÚT (0..1440) cho dễ so sánh/cộng trừ.
 * "17:30" ↔ 1050. Cho phép "24:00" (= 1440) làm giờ đóng cửa / giờ kết thúc khung cuối ngày.
 */
class ThoiGian
{
    public const BUOC_PHUT = 15; // mọi mốc giờ phải là bội số 15 phút (khớp ô lịch 15 phút ở giai đoạn 3)

    public static function phut(string $gio): int
    {
        [$h, $m] = array_map('intval', array_slice(explode(':', $gio), 0, 2));

        return $h * 60 + $m;
    }

    public static function chuoi(int $phut): string
    {
        return sprintf('%02d:%02d', intdiv($phut, 60), $phut % 60);
    }

    /** Chuỗi lưu CSDL: "17:30:00". */
    public static function csdl(int $phut): string
    {
        return self::chuoi($phut).':00';
    }

    /** Danh sách mốc "00:00", "00:15", ... "24:00" cho ô chọn giờ. */
    public static function cacMoc(int $tu = 0, int $den = 1440): array
    {
        $kq = [];
        for ($p = $tu; $p <= $den; $p += self::BUOC_PHUT) {
            $kq[] = self::chuoi($p);
        }

        return $kq;
    }

    /** Chuẩn hoá giờ người dùng gõ: "6", "6h", "6:5", "0630", "6h30", "06.30" → "06:00", ..., "06:30". Sai thì trả về null. */
    public static function chuanHoa(?string $gio): ?string
    {
        $g = strtolower(trim((string) $gio));
        if ($g === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2})\s*(?:[:h.]\s*(\d{1,2})?)?\s*(?:p|ph|phut)?$/', $g, $m)) {
            [$h, $p] = [(int) $m[1], (int) ($m[2] ?? 0)];
        } elseif (preg_match('/^(\d{2})(\d{2})$/', $g, $m)) {
            [$h, $p] = [(int) $m[1], (int) $m[2]];
        } else {
            return null;
        }
        if ($p > 59 || $h * 60 + $p > 1440) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $p);
    }

    public static function hopLe(string $gio): bool
    {
        if (! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $gio)) {
            return false;
        }
        $p = self::phut($gio);

        return $p >= 0 && $p <= 1440 && $p % self::BUOC_PHUT === 0;
    }

    /** Hai khoảng [a1,a2) và [b1,b2) có giao nhau không. */
    public static function chong(int $a1, int $a2, int $b1, int $b2): bool
    {
        return $a1 < $b2 && $b1 < $a2;
    }
}
