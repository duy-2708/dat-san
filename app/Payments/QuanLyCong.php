<?php

namespace App\Payments;

use InvalidArgumentException;

/** Chọn lớp cổng theo tên. Thêm SePay: 'sepay' => SePayCong::class. */
class QuanLyCong
{
    public const CAC_CONG = [
        'payos' => PayOsCong::class,
    ];

    public function cong(string $ten): CongThanhToan
    {
        $lop = self::CAC_CONG[$ten] ?? throw new InvalidArgumentException("Không có cổng {$ten}");

        return app($lop);
    }
}
