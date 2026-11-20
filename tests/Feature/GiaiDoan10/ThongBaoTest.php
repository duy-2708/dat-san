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

    public function test_nhan_coc_ngoai_thu_tien_san_va_doanh_thu_thang(): void
    {
        $khach = User::factory()->create();
        $this->datOnline($khach, $this->a)->assertSessionHasNoErrors();
        $don = DatSan::firstOrFail();

        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/xac-nhan-coc", ['ngoai' => 1])->assertSessionHasNoErrors();
        $this->assertSame('da_dat', $don->fresh()->trang_thai);
        $this->assertSame('tien_mat', \App\Models\GiaoDich::where('dat_san_id', $don->id)->value('phuong_thuc'));

        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/da-thu")->assertSessionHasNoErrors();
        $this->assertSame(210000, $don->fresh()->tien_thu_tai_san);
        $this->assertSame(0, $don->fresh()->conLaiPhai());
        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/da-thu")->assertSessionHasErrors();

        $this->actingAs($this->chu)->get('/quan-ly')->assertOk()
            ->assertSee('Doanh thu tháng 11/2026')->assertSee('90.000đ')->assertSee('210.000đ')->assertSee('300.000đ');
    }

    public function test_thong_bao_cung_don_duoc_gop(): void
    {
        $khach = User::factory()->create();
        $this->datOnline($khach, $this->a);
        $don = DatSan::firstOrFail();
        $this->actingAs($khach)->post("/dat-san/{$don->ma_don}/da-chuyen")->assertSessionHasNoErrors();

        $this->assertSame(2, $this->chu->notifications()->count());
        $this->actingAs($this->chu)->get('/')->assertSee('2 cập nhật')->assertSee('Cần xử lý (1)');
        $this->actingAs($this->chu)->get('/thong-bao?loc=can')->assertSee('Khách báo đã chuyển · 90.000đ');

        $id = $this->chu->notifications()->first()->id;
        $this->actingAs($this->chu)->get('/thong-bao/'.$id);
        $this->assertSame(0, $this->chu->unreadNotifications()->count());
    }

    public function test_thu_tien_nhap_so_khac_va_dat_tai_cho_mo_dung_ngay(): void
    {
        $khach = User::factory()->create();
        app(DatSanService::class)->ghiDon($this->a, '2026-11-19', 17 * 60, 18 * 60, [
            'khach_id' => $khach->id, 'gia_chot' => 300000, 'tien_coc' => 90000, 'nguon' => 'online',
            'trang_thai' => 'da_dat', 'xac_nhan_coc_luc' => now(),
        ]);
        $don = DatSan::firstOrFail();
        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/da-thu", ['so_tien' => '250.000'])->assertSessionHasNoErrors();
        $this->assertSame(250000, $don->fresh()->tien_thu_tai_san);

        $k = $this->khung($this->a, '19:00');
        $this->actingAs($this->chu)->get("/san/{$this->a->slug}")->assertSee('+ Đặt tại chỗ')
            ->assertSee(route('quan-ly.san.lich', ['san' => $this->a, 'tu' => '2026-11-18', 'mo' => $k->id]));
        $this->actingAs($this->chu)->get(route('quan-ly.san.lich', ['san' => $this->a, 'tu' => '2026-11-19', 'mo' => $k->id]))
            ->assertOk()->assertSee('data-khung="'.$k->id.'"', false)->assertSee('Đã thu 250.000');
    }

    public function test_toi_gio_da_hien_cho_thu_tien_san(): void
    {
        $this->actingAs($this->chu)->post("/quan-ly/san/{$this->a->id}/dat-tai-cho", [
            'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->a, '17:00')->id, 'ten' => 'Anh Ba', 'sdt' => '0911222333',
        ])->assertSessionHasNoErrors();
        $don = DatSan::firstOrFail();

        $this->actingAs($this->chu)->get('/quan-ly/dat-san')->assertSee('Không có lượt nào chờ thu')->assertSee('Thu sau khi tới giờ đá');
        $this->travelTo(\Carbon\Carbon::parse('2026-11-18 18:05'));
        $this->actingAs($this->chu)->get('/quan-ly/dat-san')->assertSee('Chờ thu tiền sân')->assertSee($don->ma_don)->assertDontSee('Không có lượt nào chờ thu');
        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/da-thu", ['so_tien' => '300.000'])->assertSessionHasNoErrors();
        $this->actingAs($this->chu)->get('/quan-ly/dat-san')->assertSee('Không có lượt nào chờ thu');
    }
}
