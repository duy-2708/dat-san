<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\HoSoChuSan;
use App\Payments\QuanLyCong;
use App\Support\VietQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Chủ sân chọn 1 trong 2 cách nhận cọc:
 *  A. vietqr: VietQR tài khoản riêng, nhân viên bấm xác nhận.
 *  B. payos : cổng PayOS của chính chủ sân, webhook tự xác nhận.
 */
class ThanhToanController extends Controller
{
    public function edit(Request $request): View
    {
        $hoSo = $request->user()->hoSoChuSan;
        $qrThu = $hoSo->ngan_hang_bin && $hoSo->so_tai_khoan
            ? VietQr::svg(VietQr::chuoi($hoSo->ngan_hang_bin, $hoSo->so_tai_khoan, 10000, 'THU QR'), 200)
            : null;

        return view('quan-ly.thanh-toan', [
            'hoSo' => $hoSo,
            'qrThu' => $qrThu,
            'choPhepCong' => $this->choPhepCong($hoSo),
            'webhookUrl' => $hoSo->webhook_token ? route('webhook.nhan', ['payos', $hoSo->webhook_token]) : null,
            'daCoKhoa' => (bool) ($hoSo->payos_client_id && $hoSo->payos_api_key && $hoSo->payos_checksum_key),
        ]);
    }

    public function update(Request $request, QuanLyCong $quanLyCong): RedirectResponse
    {
        $hoSo = $request->user()->hoSoChuSan;

        $d = $request->validate([
            'phuong_thuc_tt' => ['nullable', Rule::in(['vietqr', 'payos'])],
            'ngan_hang_bin' => ['required_unless:phuong_thuc_tt,payos', 'nullable', Rule::in(array_keys(VietQr::NGAN_HANG))],
            'so_tai_khoan' => ['required_unless:phuong_thuc_tt,payos', 'nullable', 'regex:/^[0-9]{6,19}$/'],
            'ten_chu_tai_khoan' => ['required_unless:phuong_thuc_tt,payos', 'nullable', 'string', 'max:100'],
            'sdt_lien_he' => ['nullable', 'string', 'max:15'],
            'phut_giu_cho' => ['required', 'integer', 'min:5', 'max:60'],
            'gio_huy_hoan_coc' => ['required', 'integer', 'min:0', 'max:168'],
            'payos_client_id' => ['nullable', 'string', 'max:100'],
            'payos_api_key' => ['nullable', 'string', 'max:100'],
            'payos_checksum_key' => ['nullable', 'string', 'max:100'],
        ], ['so_tai_khoan.regex' => 'Số tài khoản chỉ gồm 6–19 chữ số.']);

        $phuongThuc = $d['phuong_thuc_tt'] ?? 'vietqr';

        if ($phuongThuc === 'payos') {
            if (! $this->choPhepCong($hoSo)) {
                throw ValidationException::withMessages(['phuong_thuc_tt' => 'Gói hiện tại chưa gồm cổng thanh toán tự động.']);
            }
            // Ô khoá để trống = giữ khoá đã lưu (khoá đã lưu không bao giờ hiện lại trên trang)
            foreach (['payos_client_id', 'payos_api_key', 'payos_checksum_key'] as $cot) {
                if (! empty($d[$cot])) {
                    $hoSo->$cot = trim($d[$cot]);
                }
                if (! $hoSo->$cot) {
                    throw ValidationException::withMessages([$cot => 'Nhập đủ Client ID, API Key và Checksum Key lấy từ trang PayOS.']);
                }
            }
            $hoSo->webhook_token ??= Str::random(40);
        }

        $hoSo->fill([
            'phuong_thuc_tt' => $phuongThuc,
            'ngan_hang_bin' => $d['ngan_hang_bin'] ?? $hoSo->ngan_hang_bin,
            'so_tai_khoan' => $d['so_tai_khoan'] ?? $hoSo->so_tai_khoan,
            'ten_chu_tai_khoan' => isset($d['ten_chu_tai_khoan']) ? VietQr::tenKhongDau($d['ten_chu_tai_khoan']) : $hoSo->ten_chu_tai_khoan,
            'sdt_lien_he' => $d['sdt_lien_he'] ?? $hoSo->sdt_lien_he,
            'phut_giu_cho' => $d['phut_giu_cho'],
            'gio_huy_hoan_coc' => $d['gio_huy_hoan_coc'],
        ]);
        $hoSo->save();

        if ($phuongThuc !== 'payos') {
            return back()->with('thanh_cong', 'Đã lưu. Hãy quét thử mã QR 10.000đ bằng app ngân hàng để kiểm tra đúng tên chủ tài khoản (KHÔNG cần chuyển).');
        }

        $url = route('webhook.nhan', ['payos', $hoSo->webhook_token]);
        try {
            $quanLyCong->cong('payos')->dangKyWebhook($url, $hoSo);

            return back()->with('thanh_cong', 'Đã lưu và đăng ký webhook với PayOS. Tiền cọc về sẽ tự xác nhận.');
        } catch (\Throwable $e) {
            return back()->with('thanh_cong', 'Đã lưu khoá PayOS, nhưng chưa đăng ký được webhook tự động ('.$e->getMessage()
                .'). Hãy dán URL webhook bên dưới vào trang PayOS. Lưu ý: máy local (127.0.0.1) PayOS không gọi tới được — chỉ chạy khi web đã lên tên miền HTTPS.');
        }
    }

    private function choPhepCong(HoSoChuSan $hoSo): bool
    {
        $goi = $hoSo->goiApDung();

        return ! $goi || $goi->cho_phep_cong_tu_dong; // đang miễn phí (chưa gán gói) → cho dùng
    }
}
