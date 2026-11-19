<?php

namespace App\Payments;

/** Dữ liệu đã kiểm chữ ký từ webhook, chuẩn hoá cho mọi cổng. */
final class KetQuaWebhook
{
    public function __construct(
        public readonly bool $thanhCong,     // cổng báo giao dịch thành công
        public readonly int $maGiaoDich,     // = giao_dich.id (orderCode)
        public readonly int $soTien,
        public readonly string $maThamChieu, // mã giao dịch ngân hàng
        public readonly array $duLieu,
    ) {
    }

    /** Khoá chống xử lý trùng khi cổng gửi lặp. */
    public function khoaSuKien(string $cong): string
    {
        return $cong.':'.$this->maGiaoDich.':'.$this->maThamChieu;
    }
}
