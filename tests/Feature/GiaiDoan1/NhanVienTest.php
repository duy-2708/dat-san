<?php

namespace Tests\Feature\GiaiDoan1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NhanVienTest extends TestCase
{
    use RefreshDatabase;

    public function test_chu_san_tao_nhan_vien_gan_voi_chinh_minh(): void
    {
        $chuA = User::factory()->chuSan()->create();
        $chuB = User::factory()->chuSan()->create();

        $this->actingAs($chuA)->post('/quan-ly/nhan-vien', [
            'name' => 'Nhân Viên A',
            'email' => 'nva@example.com',
            'so_dien_thoai' => '0977111222',
            'password' => 'matkhau123',
            'chu_san_id' => $chuB->id,   // cố tình gửi kèm → phải bị bỏ qua
            'vai_tro' => 'admin',
        ])->assertRedirect(route('quan-ly.nhan-vien.index'));

        $nv = User::where('email', 'nva@example.com')->firstOrFail();
        $this->assertSame(User::NHAN_VIEN, $nv->vai_tro);
        $this->assertSame($chuA->id, $nv->chu_san_id);
    }

    public function test_danh_sach_chi_hien_nhan_vien_cua_minh(): void
    {
        $chuA = User::factory()->chuSan()->create();
        $chuB = User::factory()->chuSan()->create();
        $nvA = User::factory()->nhanVienCua($chuA)->create(['name' => 'Nhân viên của A']);
        $nvB = User::factory()->nhanVienCua($chuB)->create(['name' => 'Nhân viên của B']);

        $this->actingAs($chuA)->get('/quan-ly/nhan-vien')
            ->assertOk()
            ->assertSee($nvA->name)
            ->assertDontSee($nvB->name);
    }

    public function test_chu_san_khong_khoa_duoc_nhan_vien_cua_nguoi_khac(): void
    {
        $chuA = User::factory()->chuSan()->create();
        $nvB = User::factory()->nhanVienCua(User::factory()->chuSan()->create())->create();

        $this->actingAs($chuA)->patch("/quan-ly/nhan-vien/{$nvB->id}/khoa")->assertForbidden();
        $this->assertSame('hoat_dong', $nvB->fresh()->trang_thai);
    }

    public function test_chu_san_khong_khoa_duoc_khach_qua_route_nhan_vien(): void
    {
        $chuA = User::factory()->chuSan()->create();
        $khach = User::factory()->create();

        $this->actingAs($chuA)->patch("/quan-ly/nhan-vien/{$khach->id}/khoa")->assertForbidden();
    }

    public function test_chu_san_khoa_va_mo_nhan_vien_cua_minh(): void
    {
        $chuA = User::factory()->chuSan()->create();
        $nv = User::factory()->nhanVienCua($chuA)->create();

        $this->actingAs($chuA)->patch("/quan-ly/nhan-vien/{$nv->id}/khoa")->assertRedirect();
        $this->assertSame('bi_khoa', $nv->fresh()->trang_thai);

        $this->actingAs($chuA)->patch("/quan-ly/nhan-vien/{$nv->id}/khoa")->assertRedirect();
        $this->assertSame('hoat_dong', $nv->fresh()->trang_thai);
    }
}
