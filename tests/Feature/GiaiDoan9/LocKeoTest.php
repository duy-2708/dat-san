<?php

namespace Tests\Feature\GiaiDoan9;

use App\Models\Doi;
use App\Models\Keo;
use App\Models\User;
use App\Services\DoiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\GiaiDoan3\CoSanDeDat;
use Tests\TestCase;

class LocKeoTest extends TestCase
{
    use CoSanDeDat;
    use RefreshDatabase;

    private User $truong;
    private Doi $doi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();
        $this->truong = User::factory()->create(['name' => 'Phạm Tiến Đạo']);
        $this->doi = app(DoiService::class)->taoDoi($this->truong, ['ten' => 'FC Phú Nhuận'], null);
    }

    private function dang(string $gio, $san = null)
    {
        $san ??= $this->a;

        return $this->actingAs($this->truong)->post('/keo/truoc', [
            'san_id' => $san->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($san, $gio)->id,
            'doi_id' => $this->doi->id, 'ty_le_thua' => 70, 'trinh_do' => 'trung_binh', 'cong_khai' => 1,
        ]);
    }

    public function test_mot_doi_khong_dang_hai_keo_trung_gio(): void
    {
        $this->dang('17:00')->assertSessionHasNoErrors();
        $this->dang('17:00', $this->b)->assertSessionHasErrors('keo');      // sân khác cùng giờ cũng chặn
        $this->dang('19:00')->assertSessionHasNoErrors();
        $this->assertSame(2, Keo::count());
    }

    public function test_the_keo_hien_nguoi_ra_keo_nhan_va_loc(): void
    {
        $this->dang('17:00');
        $tinh = $this->a->xaPhuong->tinh_thanh_id;

        $this->get('/keo')->assertOk()->assertSee('Người ra kèo')->assertSee('Phạm Tiến Đạo')->assertSee('Chưa đặt sân');
        $this->get('/keo?tinh='.$tinh)->assertSee('FC Phú Nhuận');
        $this->get('/keo?tinh=999999')->assertDontSee('FC Phú Nhuận');
        $this->get('/keo?kieu=da_dat')->assertDontSee('FC Phú Nhuận');
        $this->get('/keo?ngay=ngay_mai')->assertDontSee('FC Phú Nhuận');
        $this->get('/keo?loai_san=7')->assertDontSee('FC Phú Nhuận');

        // Trang chủ và lịch sân đều thấy kèo
        $this->get('/')->assertSee('kèo đang tìm đối');
        $this->get('/san/'.$this->a->slug)->assertSee('Có kèo tìm đối');
    }

    public function test_san_tao_keo_giup_doi_nho_va_nguoi_nhan_phai_co_doi(): void
    {
        $this->actingAs($this->chu)->get('/keo')->assertSee('Tạo kèo (sân làm giùm)');
        $this->actingAs($this->chu)->get('/keo/san-tao?san='.$this->a->id.'&ngay=2026-11-18')->assertOk()->assertSee('Tạo kèo');

        $this->actingAs($this->chu)->post('/keo/truoc', [
            'san_id' => $this->a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->a, '20:00')->id,
            'ty_le_thua' => 50, 'trinh_do' => 'kha', 'doi_nho' => 'FC Phú Nhuận',
        ])->assertSessionHasNoErrors();
        $k = Keo::firstOrFail();
        $this->assertSame($this->doi->id, (int) $k->doi_a_id);
        $this->assertSame($this->truong->id, (int) $k->dai_dien_a_id);

        $khongDoi = User::factory()->create();
        $this->actingAs($khongDoi)->post("/keo/{$k->id}/xin", [])->assertSessionHasErrors('keo');
    }
}
