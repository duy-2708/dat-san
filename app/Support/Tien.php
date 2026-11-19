<?php

namespace App\Support;

/** Tiền người dùng gõ có dấu chấm ngăn cách: "100.000" / "100,000đ" / "100k" → 100000. */
class Tien
{
    public static function so(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = mb_strtolower(trim((string) $v));
        $nghin = str_ends_with($s, 'k');
        $so = preg_replace('/\D/', '', $s);
        if ($so === '') {
            return null;
        }

        return (int) $so * ($nghin ? 1000 : 1);
    }

    public static function chuoi(int $so): string
    {
        return number_format($so, 0, ',', '.');
    }
}
