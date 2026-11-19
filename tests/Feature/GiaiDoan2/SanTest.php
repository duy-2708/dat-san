<?php

namespace Tests\Feature\GiaiDoan2;

use App\Models\GoiThueBao;
use App\Models\San;
use App\Models\User;
use App\Models\XaPhuong;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanTest extends TestCase
{
    use RefreshDatabase;

    private function duLieu(array $ghiDe = []): array
    {
        return array_merge([
            'ten' => 'Sân 5A',
            'loai_san' => 5,
            'xa_phuong_id' => XaPhuong::factory()->create()->id,
            'dia_chi_chi_tiet' => '12 Lê Lợi',
            'tien_ich' => ['wifi', 'gui_xe'],
            'gio_mo_cua' => '06:00',
            'gio_dong_cua' => '24:00',
        ], $ghiDe);
    }

    public function test_chu_san_tao_san_cua_minh(): void
    {
        $chu = User::factory()->chuSan()->create();

        $this->actingAs($chu)->post('/quan-ly/san', $this->duLieu(['chu_san_id' => 999]))
            ->assertRedirect();

        $san = San::firstOrFail();
        $this->assertSame($chu->id, $san->chu_san_id);         // không lấy chu_san_id từ form
        $this->assertSame(['wifi', 'gui_xe'], $san->tien_ich);
        $this->assertSame('24:00', substr($san->gio_dong_cua, 0, 5));
    }

    public function test_du_lieu_sai_bi_tu_choi(): void
    {
        $chu = User::factory()->chuSan()->create();

        $this->actingAs($chu)->post('/quan-ly/san', $this->duLieu(['loai_san' => 6]))->assertSessionHasErrors('loai_san');
        $this->actingAs($chu)->post('/quan-ly/san', $this->duLieu(['gio_mo_cua' => '22:00', 'gio_dong_cua' => '08:00']))->assertSessionHasErrors('gio_dong_cua');
        $this->actingAs($chu)->post('/quan-ly/san', $this->duLieu(['tien_ich' => ['be_boi']]))->assertSessionHasErrors('tien_ich.0');
        $this->assertSame(0, San::count());
    }

    public function test_chu_san_khong_xem_va_sua_duoc_san_nguoi_khac(): void
    {
        $chuA = User::factory()->chuSan()->create();
        $sanB = San::factory()->create();

        $this->actingAs($chuA)->get("/quan-ly/san/{$sanB->id}/sua")->assertForbidden();
        $this->actingAs($chuA)->get("/quan-ly/san/{$sanB->id}/khung-gio")->assertForbidden();
        $this->actingAs($chuA)->put("/quan-ly/san/{$sanB->id}", $this->duLieu(['ten' => 'Chiếm']))->assertForbidden();
        $this->actingAs($chuA)->patch("/quan-ly/san/{$sanB->id}/trang-thai")->assertForbidden();
        $this->actingAs($chuA)->delete("/quan-ly/san/{$sanB->id}")->assertForbidden();

        $this->assertNotSame('Chiếm', $sanB->fresh()->ten);
    }

    public function test_danh_sach_chi_hien_san_cua_minh(): void
    {
        $chuA = User::factory()->chuSan()->create();
        San::factory()->cua($chuA)->create(['ten' => 'Sân Của A']);
        San::factory()->create(['ten' => 'Sân Của B']);

        $this->actingAs($chuA)->get('/quan-ly/san')->assertOk()->assertSee('Sân Của A')->assertDontSee('Sân Của B');
    }

    public function test_nhan_vien_xem_duoc_nhung_khong_sua_duoc(): void
    {
        $chu = User::factory()->chuSan()->create();
        $nv = User::factory()->nhanVienCua($chu)->create();
        $san = San::factory()->cua($chu)->create(['ten' => 'Sân Của Chủ']);

        $this->actingAs($nv)->get('/quan-ly/san')->assertOk()->assertSee('Sân Của Chủ');
        $this->actingAs($nv)->get("/quan-ly/san/{$san->id}/khung-gio")->assertOk();
        $this->actingAs($nv)->get('/quan-ly/san/tao')->assertForbidden();
        $this->actingAs($nv)->get("/quan-ly/san/{$san->id}/sua")->assertForbidden();
    }

    public function test_khach_khong_vao_duoc(): void
    {
        $this->actingAs(User::factory()->create())->get('/quan-ly/san')->assertForbidden();
    }

    public function test_tam_ngung_va_mo_lai(): void
    {
        $chu = User::factory()->chuSan()->create();
        $san = San::factory()->cua($chu)->create();

        $this->actingAs($chu)->patch("/quan-ly/san/{$san->id}/trang-thai");
        $this->assertSame('tam_ngung', $san->fresh()->trang_thai);
        $this->actingAs($chu)->patch("/quan-ly/san/{$san->id}/trang-thai");
        $this->assertSame('hoat_dong', $san->fresh()->trang_thai);
    }

    public function test_gioi_han_so_san_theo_goi(): void
    {
        $chu = User::factory()->chuSan()->create();
        $goi = GoiThueBao::create(['ten' => 'Thử', 'gia_thang' => 0, 'so_san_toi_da' => 1, 'so_nhan_vien_toi_da' => 1]);
        $chu->hoSoChuSan->goi_thue_bao_id = $goi->id;
        $chu->hoSoChuSan->save();
        San::factory()->cua($chu)->create();

        $this->actingAs($chu)->post('/quan-ly/san', $this->duLieu())->assertSessionHasErrors('goi');
        $this->assertSame(1, San::count());
    }

    public function test_api_xa_phuong_theo_tinh(): void
    {
        $xp = XaPhuong::factory()->create(['ten' => 'Phường Thử']);
        XaPhuong::factory()->create(['ten' => 'Phường Tỉnh Khác']);

        $this->getJson('/dia-chi/xa-phuong?tinh_thanh_id='.$xp->tinh_thanh_id)
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.ten', 'Phường Thử');
    }
}
