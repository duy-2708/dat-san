<?php

namespace App\Payments;

use App\Models\GiaoDich;
use App\Models\HoSoChuSan;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PayOS (payos.vn). Mỗi chủ sân dùng KÊNH THANH TOÁN CỦA CHÍNH HỌ (client id / api key / checksum key),
 * tiền vào thẳng tài khoản ngân hàng chủ sân đã liên kết với PayOS. Website không giữ tiền.
 */
class PayOsCong implements CongThanhToan
{
    public const API = 'https://api-merchant.payos.vn';

    public function ten(): string
    {
        return 'payos';
    }

    public function taoThanhToan(GiaoDich $gd, string $noiDung, int $hetHanUnix, HoSoChuSan $hoSo): array
    {
        $body = [
            'orderCode' => $gd->id,
            'amount' => (int) $gd->so_tien,
            'description' => substr($noiDung, 0, 25),
            'returnUrl' => route('dat-san.thanh-toan', $gd->datSan),
            'cancelUrl' => route('dat-san.thanh-toan', $gd->datSan),
            'expiredAt' => $hetHanUnix,
        ];
        // Chữ ký tạo link: 5 trường, sắp theo tên, nối "key=value&..."
        $body['signature'] = self::ky([
            'amount' => $body['amount'], 'cancelUrl' => $body['cancelUrl'], 'description' => $body['description'],
            'orderCode' => $body['orderCode'], 'returnUrl' => $body['returnUrl'],
        ], $hoSo->payos_checksum_key);

        $res = Http::withHeaders($this->headers($hoSo))->timeout(15)
            ->post(self::API.'/v2/payment-requests', $body)->json();

        if (($res['code'] ?? null) !== '00' || empty($res['data'])) {
            throw new RuntimeException('PayOS: '.($res['desc'] ?? 'không phản hồi'));
        }

        return [
            'ma_cong' => (string) $res['data']['paymentLinkId'],
            'checkout_url' => $res['data']['checkoutUrl'] ?? null,
            'qr' => $res['data']['qrCode'] ?? null,
        ];
    }

    public function docWebhook(array $payload, HoSoChuSan $hoSo): ?KetQuaWebhook
    {
        $data = $payload['data'] ?? null;
        $chuKy = $payload['signature'] ?? null;
        if (! is_array($data) || ! is_string($chuKy) || ! $hoSo->payos_checksum_key) {
            return null;
        }

        if (! hash_equals(self::ky($data, $hoSo->payos_checksum_key), $chuKy)) {
            return null;
        }

        return new KetQuaWebhook(
            thanhCong: ($payload['code'] ?? null) === '00' && ($data['code'] ?? '00') === '00',
            maGiaoDich: (int) ($data['orderCode'] ?? 0),
            soTien: (int) ($data['amount'] ?? 0),
            maThamChieu: (string) ($data['reference'] ?? ''),
            duLieu: $data,
        );
    }

    public function dangKyWebhook(string $url, HoSoChuSan $hoSo): void
    {
        $res = Http::withHeaders($this->headers($hoSo))->timeout(15)
            ->post(self::API.'/confirm-webhook', ['webhookUrl' => $url])->json();

        if (($res['code'] ?? null) !== '00') {
            throw new RuntimeException('PayOS: '.($res['desc'] ?? 'không xác nhận được webhook'));
        }
    }

    /**
     * Chữ ký PayOS: sắp các trường theo tên (a→z), nối "key=value" bằng "&", HMAC-SHA256 với checksum key.
     * Giá trị null → chuỗi rỗng; mảng → JSON.
     */
    public static function ky(array $truong, string $checksumKey): string
    {
        ksort($truong);
        $chuoi = collect($truong)->map(function ($v, $k) {
            if ($v === null) {
                $v = '';
            } elseif (is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }

            return $k.'='.$v;
        })->implode('&');

        return hash_hmac('sha256', $chuoi, $checksumKey);
    }

    private function headers(HoSoChuSan $hoSo): array
    {
        return ['x-client-id' => (string) $hoSo->payos_client_id, 'x-api-key' => (string) $hoSo->payos_api_key];
    }
}
