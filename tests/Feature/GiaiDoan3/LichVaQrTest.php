<?php

namespace Tests\Feature\GiaiDoan3;

use App\Models\User;
use App\Support\VietQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LichVaQrTest extends TestCase
{
    use RefreshDatabase, CoSanDeDat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();
    }

    public function test_nguoi_chua_dang_nhap_thay_trang_thai_nhung_khong_thay_ai_dat(): void
    {
        $khach = User::factory()->create(['name' => 'Tên Bí Mật', 'so_dien_thoai' => '0977000111']);
        $this->datOnline($khach, $this->a, '17:00');
        $this->post('/logout');

        $this->get('/san/'.$this->a->slug)
            ->assertOk()
            ->assertSee('Đang giữ')
            ->assertSee('Đăng nhập để đặt')
            ->assertDontSee('Tên Bí Mật')
            ->assertDontSee('0977000111');

        // Sân 7 ghép → cũng thấy bận giờ đó
        $this->get('/san/'.$this->bay->slug)->assertOk()->assertSee('Đang giữ');
    }

    public function test_chu_san_thay_ten_nguoi_dat_tren_lich(): void
    {
        $khach = User::factory()->create(['name' => 'Tên Khách Đặt']);
        $this->datOnline($khach, $this->a, '17:00');

        $this->actingAs($this->chu)->get("/quan-ly/san/{$this->a->id}/lich")->assertOk()->assertSee('Tên Khách Đặt');
        $this->actingAs($this->chu)->get('/quan-ly/dat-san')->assertOk()->assertSee('Tên Khách Đặt');
    }

    public function test_cai_dat_thanh_toan_chu_san(): void
    {
        $this->actingAs($this->chu)->put('/quan-ly/thanh-toan', [
            'ngan_hang_bin' => '970423', 'so_tai_khoan' => '00001234567', 'ten_chu_tai_khoan' => 'Trần Thị Bé',
            'phut_giu_cho' => 20, 'gio_huy_hoan_coc' => 12,
        ])->assertSessionHasNoErrors();

        $hs = $this->chu->hoSoChuSan->fresh();
        $this->assertSame('TRAN THI BE', $hs->ten_chu_tai_khoan);
        $this->assertSame(20, (int) $hs->phut_giu_cho);

        $this->actingAs($this->chu)->put('/quan-ly/thanh-toan', [
            'ngan_hang_bin' => '999999', 'so_tai_khoan' => 'abc', 'ten_chu_tai_khoan' => 'X', 'phut_giu_cho' => 1, 'gio_huy_hoan_coc' => 1,
        ])->assertSessionHasErrors(['ngan_hang_bin', 'so_tai_khoan', 'phut_giu_cho']);

        $nv = User::factory()->nhanVienCua($this->chu)->create();
        $this->actingAs($nv)->get('/quan-ly/thanh-toan')->assertForbidden();
    }

    public function test_chuoi_vietqr_dung_chuan(): void
    {
        $this->assertSame('29B1', VietQr::crc16('123456789')); // giá trị kiểm tra chuẩn CRC-16/CCITT-FALSE

        $c = VietQr::chuoi('970436', '0123456789', 90000, 'DS000123');
        $this->assertStringStartsWith('000201010212', $c);
        $this->assertStringContainsString('0006970436', $c);       // BIN
        $this->assertStringContainsString('01100123456789', $c);   // số TK
        $this->assertStringContainsString('540590000', $c);        // số tiền
        $this->assertStringContainsString('0808DS000123', $c);     // nội dung
        $this->assertSame(VietQr::crc16(substr($c, 0, -4)), substr($c, -4));
    }
}
