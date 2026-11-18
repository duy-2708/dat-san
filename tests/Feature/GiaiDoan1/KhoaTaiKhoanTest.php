<?php

namespace Tests\Feature\GiaiDoan1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KhoaTaiKhoanTest extends TestCase
{
    use RefreshDatabase;

    public function test_dang_nhap_dung_mat_khau_thanh_cong(): void
    {
        $u = User::factory()->create(['email' => 'ok@example.com']);

        $this->post('/login', ['email' => 'OK@example.com', 'password' => 'password'])->assertRedirect('/bang-dieu-khien');
        $this->assertAuthenticatedAs($u);
    }

    public function test_tai_khoan_bi_khoa_khong_dang_nhap_duoc(): void
    {
        User::factory()->biKhoa()->create(['email' => 'khoa@example.com']);

        $this->post('/login', ['email' => 'khoa@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_dang_dang_nhap_ma_bi_khoa_thi_bi_dang_xuat_ngay(): void
    {
        $khach = User::factory()->create();
        $this->actingAs($khach)->get('/tai-khoan')->assertOk();

        $khach->trang_thai = 'bi_khoa';
        $khach->save();

        $this->actingAs($khach)->get('/tai-khoan')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_khoa_chu_san_thi_nhan_vien_cung_bi_chan(): void
    {
        $chu = User::factory()->chuSan()->create();
        $nv = User::factory()->nhanVienCua($chu)->create();
        $chu->trang_thai = 'bi_khoa';
        $chu->save();

        $this->actingAs($nv)->get('/quan-ly')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $nv->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_admin_khoa_va_mo_khoa_khach(): void
    {
        $admin = User::factory()->admin()->create();
        $khach = User::factory()->create();

        $this->actingAs($admin)->patch("/admin/tai-khoan/{$khach->id}/khoa", ['ly_do_khoa' => 'Spam'])->assertRedirect();
        $this->assertSame('bi_khoa', $khach->fresh()->trang_thai);

        $this->actingAs($admin)->patch("/admin/tai-khoan/{$khach->id}/mo-khoa")->assertRedirect();
        $this->assertSame('hoat_dong', $khach->fresh()->trang_thai);
    }

    public function test_admin_khong_khoa_duoc_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $admin2 = User::factory()->admin()->create();

        $this->actingAs($admin)->patch("/admin/tai-khoan/{$admin2->id}/khoa")->assertForbidden();
    }

    public function test_chu_san_khong_goi_duoc_route_khoa_cua_admin(): void
    {
        $chu = User::factory()->chuSan()->create();
        $khach = User::factory()->create();

        $this->actingAs($chu)->patch("/admin/tai-khoan/{$khach->id}/khoa")->assertForbidden();
        $this->assertSame('hoat_dong', $khach->fresh()->trang_thai);
    }
}
