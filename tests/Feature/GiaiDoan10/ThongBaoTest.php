<?php

namespace Tests\Feature\GiaiDoan10;

use App\Models\CoSo;
use App\Models\DatSan;
use App\Models\User;
use App\Models\XaPhuong;
use App\Notifications\ThongBao;
use App\Notifications\ThongBaoEmail;
use App\Services\DatSanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\GiaiDoan3\CoSanDeDat;
use Tests\TestCase;

class ThongBaoTest extends TestCase
{
    use CoSanDeDat, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();
    }

    public function test_chu_san_nhan_thong_bao_don_moi_va_chuong(): void
    {
        $this->datOnline(User::factory()->create(['name' => 'Khách Mới']), $this->a)->assertSessionHasNoErrors();
        $tb = $this->chu->notifications()->firstOrFail();
        $this->assertStringContainsString('Đơn đặt mới', $tb->data['tieu_de']);

        $this->actingAs($this->chu)->get('/thong-bao')->assertOk()->assertSee('Khách Mới');
        $this->actingAs($this->chu)->get('/')->assertSee('bi-bell-fill', false);
        $this->actingAs($this->chu)->get('/thong-bao/'.$tb->id)->assertRedirect();
        $this->assertNotNull($tb->fresh()->read_at);
    }

    public function test_chu_san_huy_don_khach_nhan_chuong_va_email(): void
    {
        $khach = User::factory()->create();
        $this->datOnline($khach, $this->a);
        $don = DatSan::firstOrFail();
        Notification::fake();
        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/huy", ['ly_do' => 'Sân hỏng đèn'])->assertSessionHasNoErrors();
        Notification::assertSentTo($khach, ThongBao::class, fn ($n) => str_contains($n->tieuDe, 'bị huỷ') && str_contains($n->noiDung, 'Sân hỏng đèn'));
        Notification::assertSentTo($khach, ThongBaoEmail::class);
    }

    public function test_tra_tai_san_hom_nay_duoc_vien_va_cham_do(): void
    {
        $khach = User::factory()->create();
        app(DatSanService::class)->ghiDon($this->a, '2026-11-18', 17 * 60, 18 * 60, [
            'khach_id' => $khach->id, 'gia_chot' => 300000, 'tien_coc' => 90000, 'nguon' => 'online',
            'trang_thai' => 'da_dat', 'xac_nhan_coc_luc' => now(),
        ]);
        $this->actingAs($khach)->get('/thanh-toan-cua-toi')->assertOk()
            ->assertSee('Trả tại sân hôm nay')->assertSee('Hôm nay 17:00')->assertSee('Mang theo 210.000đ');
    }

    public function test_tao_san_co_gia_mac_dinh_va_tao_bu_gio_thieu(): void
    {
        $this->actingAs($this->chu)->post('/quan-ly/san', [
            'ten_co_so' => 'Cơ Sở Giá', 'ten' => 'Sân G', 'loai_san' => 5, 'gia_mac_dinh' => '200.000',
            'xa_phuong_id' => XaPhuong::factory()->create()->id, 'dia_chi_chi_tiet' => '1 Trần Phú', 'gio_mo_cua' => '06:00', 'gio_dong_cua' => '23:00',
        ])->assertSessionHasNoErrors();
        $g = CoSo::where('ten', 'Cơ Sở Giá')->firstOrFail()->sans()->first();
        $this->assertSame(17, $g->khungGios()->where('loai_ngay', 'ngay_thuong')->count());
        $this->assertSame(200000, (int) $g->khungGios()->where('loai_ngay', 'cuoi_tuan')->value('gia'));

        $g->khungGios()->where('gio_bat_dau', '>=', '11:00:00')->delete();
        $this->actingAs($this->chu)->get("/quan-ly/san/{$g->id}/khung-gio")->assertSee('Còn giờ chưa có khung')->assertSee('11:00–23:00');
        $this->actingAs($this->chu)->post("/quan-ly/san/{$g->id}/khung-gio/tao-bu")->assertSessionHasNoErrors();
        $this->assertSame(17, $g->khungGios()->where('loai_ngay', 'ngay_thuong')->count());
        $this->assertSame(200000, (int) $g->khungGios()->where('gio_bat_dau', '20:00:00')->where('loai_ngay', 'ngay_thuong')->value('gia'));
        $this->actingAs($this->chu)->get("/quan-ly/san/{$g->id}/khung-gio")->assertDontSee('Còn giờ chưa có khung');
    }
}
