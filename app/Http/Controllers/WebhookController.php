<?php

namespace App\Http\Controllers;

use App\Models\GiaoDich;
use App\Models\HoSoChuSan;
use App\Payments\QuanLyCong;
use App\Services\DatSanService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cổng thanh toán gọi vào đây khi có tiền về: POST /webhook/{cong}/{token}
 * {token} riêng cho từng chủ sân → biết dùng checksum key của ai để kiểm chữ ký.
 *
 * An toàn:
 *  1. Chữ ký sai → 400, không đụng dữ liệu.
 *  2. Đối chiếu đúng giao dịch của CHÍNH chủ sân đó + đúng số tiền.
 *  3. Cổng gửi lặp → webhook_log UNIQUE(cong, khoa_su_kien) chặn xử lý lần 2.
 */
class WebhookController extends Controller
{
    public function nhan(Request $request, string $cong, string $token, QuanLyCong $quanLyCong, DatSanService $datSan): JsonResponse
    {
        abort_unless(array_key_exists($cong, QuanLyCong::CAC_CONG), 404);
        $hoSo = HoSoChuSan::where('webhook_token', $token)->first();
        abort_unless($hoSo, 404);

        $payload = $request->json()->all();
        $kq = $quanLyCong->cong($cong)->docWebhook($payload, $hoSo);

        if (! $kq) {
            $this->ghiLog($cong, 'sai_chu_ky:'.Str::uuid(), null, false, 'sai_chu_ky', $payload);

            return response()->json(['success' => false, 'message' => 'invalid signature'], 400);
        }

        // Ghi nhật ký TRƯỚC, nhờ UNIQUE mà lần gửi lặp dừng ngay tại đây
        try {
            $log = $this->ghiLog($cong, $kq->khoaSuKien($cong), null, true, 'dang_xu_ly', $payload);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['success' => true, 'message' => 'duplicate']);
        }

        $gd = GiaoDich::where('id', $kq->maGiaoDich)
            ->where('chu_san_id', $hoSo->user_id)       // chỉ giao dịch của chủ sân sở hữu token
            ->where('phuong_thuc', $cong)
            ->first();

        $ketQua = match (true) {
            ! $kq->thanhCong => 'cong_bao_that_bai',
            ! $gd => 'khong_tim_thay',                   // VD webhook thử của PayOS (orderCode 123)
            default => $datSan->nhanTienTuCong($gd, $kq->soTien, $kq->maThamChieu, $kq->duLieu),
        };

        DB::table('webhook_log')->where('id', $log)->update(['ket_qua' => $ketQua, 'giao_dich_id' => $gd?->id]);

        // Luôn trả 200 khi chữ ký đúng để cổng không gửi lại mãi
        return response()->json(['success' => true, 'message' => $ketQua]);
    }

    private function ghiLog(string $cong, string $khoa, ?int $gdId, bool $hopLe, string $ketQua, array $payload): int
    {
        return DB::table('webhook_log')->insertGetId([
            'cong' => $cong, 'khoa_su_kien' => substr($khoa, 0, 150), 'giao_dich_id' => $gdId,
            'chu_ky_hop_le' => $hopLe, 'ket_qua' => $ketQua,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'created_at' => now(),
        ]);
    }
}
