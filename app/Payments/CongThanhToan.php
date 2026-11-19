<?php

namespace App\Payments;

use App\Models\GiaoDich;
use App\Models\HoSoChuSan;

/**
 * Lớp chung cho mọi cổng thanh toán tự động (PayOS, sau này SePay...).
 * Thêm cổng mới = viết 1 lớp implements interface này + khai báo trong QuanLyCong.
 */
interface CongThanhToan
{
    /** Tên ngắn, khớp cột ho_so_chu_san.phuong_thuc_tt và webhook_log.cong. */
    public function ten(): string;

    /**
     * Tạo yêu cầu thanh toán cho 1 giao dịch.
     *
     * @return array{ma_cong: string, checkout_url: ?string, qr: ?string} qr = chuỗi VietQR để tự vẽ mã
     */
    public function taoThanhToan(GiaoDich $gd, string $noiDung, int $hetHanUnix, HoSoChuSan $hoSo): array;

    /** Kiểm tra chữ ký webhook. Trả null nếu chữ ký sai. */
    public function docWebhook(array $payload, HoSoChuSan $hoSo): ?KetQuaWebhook;

    /** Đăng ký URL webhook với cổng (nếu cổng hỗ trợ). */
    public function dangKyWebhook(string $url, HoSoChuSan $hoSo): void;
}
