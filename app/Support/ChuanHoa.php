<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Các hàm chuẩn hoá dữ liệu nhập: email, số điện thoại. */
class ChuanHoa
{
    /** Regex số di động Việt Nam sau khi chuẩn hoá (10 số, đầu 03/05/07/08/09). */
    public const REGEX_SDT = '/^0(3|5|7|8|9)\d{8}$/';

    /**
     * Email dùng để chống trùng: chữ thường, bỏ phần "+tag",
     * riêng Gmail bỏ dấu chấm (a.b@gmail.com == ab@gmail.com).
     */
    public static function email(?string $email): string
    {
        $email = Str::lower(trim((string) $email));
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$ten, $mien] = explode('@', $email, 2);
        $ten = explode('+', $ten, 2)[0];

        if (in_array($mien, ['gmail.com', 'googlemail.com'], true)) {
            $ten = str_replace('.', '', $ten);
            $mien = 'gmail.com';
        }

        return $ten.'@'.$mien;
    }

    /** "+84 901 234 567" / "84901234567" / "0901.234.567" → "0901234567". */
    public static function soDienThoai(?string $sdt): ?string
    {
        if ($sdt === null || trim($sdt) === '') {
            return null;
        }

        $so = preg_replace('/\D/', '', $sdt);
        if (str_starts_with($so, '84') && strlen($so) === 11) {
            $so = '0'.substr($so, 2);
        }

        return $so;
    }
}
