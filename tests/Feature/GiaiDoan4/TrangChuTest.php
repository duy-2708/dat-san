<?php

namespace Tests\Feature\GiaiDoan4;

use App\Models\San;
use App\Models\User;
use App\Models\XaPhuong;
use App\Services\BangGia;
use App\Services\DatSanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrangChuTest extends TestCase
{
    use RefreshDatabase;

    private XaPhuong $xaHcm;
    private XaPhuong $xaHn;
    private San $s5;
    private San $s7;
    private San $sHn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-18 08:00:00'));

        $this->xaHcm = XaPhuong::factory()->create(['ten' => 'Phường Sài Gòn']);
        $this->xaHn = XaPhuong::factory()->create(['ten' => 'Phường Hoàn Kiếm']);
        $chu = User::factory()->chuSan()->create();

        $this->s5 = San::factory()->cua($chu)->create(['ten' => 'Sân Cỏ Năm', 'xa_phuong_id' => $this->xaHcm->id]);
        $this->s7 = San::factory()->cua($chu)->loai(7)->create(['ten' => 'Sân Cỏ Bảy', 'xa_phuong_id' => $this->xaHcm->id]);
        $this->sHn = San::factory()->cua($chu)->create(['ten' => 'Sân Hà Nội', 'xa_phuong_id' => $this->xaHn->id]);
    }

    public function test_hien_moi_san_dang_mo(): void
    {
        $this->get('/')->assertOk()->assertSee('Sân Cỏ Năm')->assertSee('Sân Cỏ Bảy')->assertSee('Sân Hà Nội');
    }

    public function test_loc_theo_tinh_xa_va_loai(): void
    {
        $this->get('/?tinh='.$this->xaHcm->tinh_thanh_id)
            ->assertSee('Sân Cỏ Năm')->assertSee('Sân Cỏ Bảy')->assertDontSee('Sân Hà Nội');

        $this->get('/?tinh='.$this->xaHcm->tinh_thanh_id.'&loai=7')
            ->assertSee('Sân Cỏ Bảy')->assertDontSee('Sân Cỏ Năm');

        $this->get('/?xa='.$this->xaHn->id)->assertSee('Sân Hà Nội')->assertDontSee('Sân Cỏ Năm');

        $this->get('/?q=Bảy')->assertSee('Sân Cỏ Bảy')->assertDontSee('Sân Cỏ Năm');
    }

    public function test_an_san_tam_ngung_chu_het_han_hoac_bi_khoa(): void
    {
        $this->s5->forceFill(['trang_thai' => 'tam_ngung'])->save();
        $this->get('/')->assertDontSee('Sân Cỏ Năm')->assertSee('Sân Cỏ Bảy');

        $chuKhac = User::factory()->chuSan()->create();
        San::factory()->cua($chuKhac)->create(['ten' => 'Sân Chủ Hết Hạn']);
        $chuKhac->hoSoChuSan->forceFill(['het_han_luc' => now()->subDay()])->save();

        $chuKhoa = User::factory()->chuSan()->create(['trang_thai' => 'bi_khoa']);
        San::factory()->cua($chuKhoa)->create(['ten' => 'Sân Chủ Bị Khoá']);

        $this->get('/')->assertDontSee('Sân Chủ Hết Hạn')->assertDontSee('Sân Chủ Bị Khoá');
    }

    public function test_lich_hom_nay_khong_lo_nguoi_dat(): void
    {
        app(BangGia::class)->taoNhanh($this->s5, ['ngay_thuong'], 60, 300000);
        $k = $this->s5->khungGios()->where('gio_bat_dau', '17:00:00')->first();
        app(DatSanService::class)->ghiDon($this->s5, '2026-11-18', 17 * 60, 18 * 60, [
            'ten_khach_tai_cho' => 'Người Đặt Bí Mật', 'sdt_khach_tai_cho' => '0988777666', 'khung_gio_id' => $k->id,
            'gia_chot' => 300000, 'tien_coc' => 0, 'nguon' => 'tai_cho', 'trang_thai' => 'da_dat',
        ]);

        $this->get('/?q=Năm')
            ->assertOk()
            ->assertSee('Hôm nay: 13 khung trống')   // 17 khung (06:00–22:00), 3 khung đã tới giờ (06,07,08), 1 đã đặt → 13
            ->assertDontSee('Người Đặt Bí Mật')
            ->assertDontSee('0988777666');
    }
}
