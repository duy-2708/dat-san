<?php

namespace Tests\Feature\GiaiDoan5;

use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\GoiThueBao;
use App\Models\User;
use App\Notifications\DatSanThanhCong;
use App\Payments\PayOsCong;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\GiaiDoan3\CoSanDeDat;
use Tests\TestCase;

class PayOsTest extends TestCase
{
    use RefreshDatabase, CoSanDeDat;

    private const CHECKSUM = 'checksum-bi-mat-cua-chu-san';

    private bool $congLoi = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();

        $hoSo = $this->chu->hoSoChuSan;
        $hoSo->forceFill([
            'phuong_thuc_tt' => 'payos', 'payos_client_id' => 'cid', 'payos_api_key' => 'akey',
            'payos_checksum_key' => self::CHECKSUM, 'webhook_token' => 'token-chu-san-1',
        ])->save();

        // Giả lập API PayOS (không gọi mạng thật). $this->congLoi = true để giả lập PayOS báo lỗi.
        Http::fake(function ($req) {
            if ($this->congLoi) {
                return Http::response(['code' => '20', 'desc' => 'Thông tin không hợp lệ']);
            }
            if (str_ends_with($req->url(), '/confirm-webhook')) {
                return Http::response(['code' => '00', 'desc' => 'success']);
            }

            return Http::response([
                'code' => '00', 'desc' => 'success',
                'data' => ['paymentLinkId' => 'plink-1', 'checkoutUrl' => 'https://pay.payos.vn/web/plink-1',
                    'qrCode' => '00020101021238570010A000000727012700069704220113VQRQ0000000010208QRIBFTTA53037045405900005802VN62120808DS0000016304ABCD'],
            ]);
        });
    }

    /** Webhook có chữ ký đúng như PayOS gửi. */
    private function webhook(GiaoDich $gd, ?int $soTien = null, string $ref = 'FT123', ?string $khoa = null): array
    {
        $data = [
            'orderCode' => $gd->id, 'amount' => $soTien ?? $gd->so_tien, 'description' => 'DS000001',
            'accountNumber' => '0123456789', 'reference' => $ref, 'transactionDateTime' => '2026-11-18 08:05:00',
            'currency' => 'VND', 'paymentLinkId' => 'plink-1', 'code' => '00', 'desc' => 'success',
            'counterAccountBankId' => '', 'counterAccountBankName' => '', 'counterAccountName' => null,
            'counterAccountNumber' => null, 'virtualAccountName' => '', 'virtualAccountNumber' => '',
        ];

        return ['code' => '00', 'desc' => 'success', 'success' => true, 'data' => $data,
            'signature' => PayOsCong::ky($data, $khoa ?? self::CHECKSUM)];
    }

    private function datQuaPayos(): GiaoDich
    {
        $this->datOnline(User::factory()->create(), $this->a)->assertSessionHasNoErrors();

        return GiaoDich::firstOrFail();
    }

    public function test_dat_san_tao_link_payos_dung_chu_ky(): void
    {
        $gd = $this->datQuaPayos();

        $this->assertSame('payos', $gd->phuong_thuc);
        $this->assertSame('plink-1', $gd->ma_cong);

        Http::assertSent(function ($req) use ($gd) {
            $b = $req->data();
            $ky = hash_hmac('sha256', "amount={$b['amount']}&cancelUrl={$b['cancelUrl']}&description={$b['description']}&orderCode={$b['orderCode']}&returnUrl={$b['returnUrl']}", self::CHECKSUM);

            return str_ends_with($req->url(), '/v2/payment-requests')
                && $req->hasHeader('x-client-id', 'cid') && $req->hasHeader('x-api-key', 'akey')
                && $b['orderCode'] === $gd->id && $b['amount'] === 90000 && $b['signature'] === $ky;
        });

        $don = DatSan::firstOrFail();
        $this->actingAs($don->khach)->get("/dat-san/{$don->ma_don}/thanh-toan")
            ->assertOk()->assertSee('<svg', false)->assertSee('tự xác nhận');
    }

    public function test_webhook_hop_le_tu_chuyen_da_dat_va_gui_email(): void
    {
        Notification::fake();
        $gd = $this->datQuaPayos();

        $this->postJson('/webhook/payos/token-chu-san-1', $this->webhook($gd))->assertOk()->assertJsonPath('message', 'da_xac_nhan');

        $don = DatSan::firstOrFail();
        $this->assertSame('da_dat', $don->trang_thai);
        $this->assertSame('thanh_cong', $gd->fresh()->trang_thai);
        $this->assertSame('FT123', $gd->fresh()->ma_tham_chieu);
        Notification::assertSentTo($don->khach, DatSanThanhCong::class);
    }

    public function test_webhook_gui_lap_khong_xu_ly_hai_lan(): void
    {
        Notification::fake();
        $gd = $this->datQuaPayos();
        $wh = $this->webhook($gd);

        $this->postJson('/webhook/payos/token-chu-san-1', $wh)->assertOk();
        $this->postJson('/webhook/payos/token-chu-san-1', $wh)->assertOk()->assertJsonPath('message', 'duplicate');

        $this->assertSame(1, DB::table('webhook_log')->count());
        Notification::assertSentToTimes(DatSan::first()->khach, DatSanThanhCong::class, 1);
    }

    public function test_sai_chu_ky_bi_tu_choi(): void
    {
        $gd = $this->datQuaPayos();

        $this->postJson('/webhook/payos/token-chu-san-1', $this->webhook($gd, khoa: 'khoa-gia'))->assertStatus(400);

        // Sửa số tiền sau khi ký cũng bị phát hiện
        $wh = $this->webhook($gd);
        $wh['data']['amount'] = 1;
        $this->postJson('/webhook/payos/token-chu-san-1', $wh)->assertStatus(400);

        $this->assertSame('cho_thanh_toan', DatSan::first()->trang_thai);
    }

    public function test_sai_so_tien_khong_xac_nhan(): void
    {
        $gd = $this->datQuaPayos();

        $this->postJson('/webhook/payos/token-chu-san-1', $this->webhook($gd, soTien: 1000))
            ->assertOk()->assertJsonPath('message', 'sai_so_tien');

        $this->assertSame('cho_thanh_toan', DatSan::first()->trang_thai);
    }

    public function test_token_cua_chu_san_khac_khong_dung_duoc(): void
    {
        $gd = $this->datQuaPayos();
        $chuB = User::factory()->chuSan()->create();
        $chuB->hoSoChuSan->forceFill(['payos_checksum_key' => 'khoa-b', 'webhook_token' => 'token-b'])->save();

        // Ký đúng bằng khoá của B nhưng orderCode là giao dịch của chủ A → không tìm thấy
        $this->postJson('/webhook/payos/token-b', $this->webhook($gd, khoa: 'khoa-b'))->assertOk()->assertJsonPath('message', 'khong_tim_thay');
        $this->postJson('/webhook/payos/khong-ton-tai', $this->webhook($gd))->assertNotFound();

        $this->assertSame('cho_thanh_toan', DatSan::first()->trang_thai);
    }

    public function test_tien_ve_sau_khi_het_giu_cho_thi_cho_hoan_coc(): void
    {
        $gd = $this->datQuaPayos();
        $this->travel(20)->minutes();
        $this->artisan('dat-san:nha-cho');

        $this->postJson('/webhook/payos/token-chu-san-1', $this->webhook($gd))->assertOk()->assertJsonPath('message', 'tien_ve_sau_khi_huy');

        $this->assertSame('cho_hoan', DatSan::first()->xu_ly_coc);
    }

    public function test_cong_loi_thi_huy_giu_cho(): void
    {
        $this->congLoi = true;

        $this->datOnline(User::factory()->create(), $this->a)->assertSessionHasErrors('dat_san');

        $this->assertSame('da_huy', DatSan::first()->trang_thai);
        $this->assertSame(0, DB::table('o_lich')->count()); // ô được trả lại
    }

    public function test_luu_khoa_payos_ma_hoa_va_goi_khong_cho_phep(): void
    {
        $this->actingAs($this->chu)->put('/quan-ly/thanh-toan', [
            'phuong_thuc_tt' => 'payos', 'payos_client_id' => 'cid-moi', 'payos_api_key' => '', 'payos_checksum_key' => '',
            'phut_giu_cho' => 15, 'gio_huy_hoan_coc' => 24,
        ])->assertSessionHasNoErrors();

        $hs = $this->chu->hoSoChuSan->fresh();
        $this->assertSame('cid-moi', $hs->payos_client_id);
        $this->assertSame('akey', $hs->payos_api_key);                     // để trống = giữ khoá cũ
        $this->assertNotSame('cid-moi', DB::table('ho_so_chu_san')->where('id', $hs->id)->value('payos_client_id'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/confirm-webhook'));

        $goi = GoiThueBao::create(['ten' => 'Cơ bản', 'gia_thang' => 0, 'so_san_toi_da' => 5, 'so_nhan_vien_toi_da' => 1, 'cho_phep_cong_tu_dong' => false]);
        $hs->forceFill(['goi_thue_bao_id' => $goi->id])->save();
        $this->chu->refresh(); // bỏ hồ sơ cũ đang nhớ trong đối tượng $this->chu
        $this->actingAs($this->chu)->put('/quan-ly/thanh-toan', [
            'phuong_thuc_tt' => 'payos', 'phut_giu_cho' => 15, 'gio_huy_hoan_coc' => 24,
        ])->assertSessionHasErrors('phuong_thuc_tt');
    }
}
