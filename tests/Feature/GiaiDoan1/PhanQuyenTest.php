<?php

namespace Tests\Feature\GiaiDoan1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhanQuyenTest extends TestCase
{
    use RefreshDatabase;

    public function test_nguoi_chua_dang_nhap_bi_dua_ve_trang_dang_nhap(): void
    {
        $this->get('/quan-ly')->assertRedirect('/login');
        $this->get('/admin/chu-san')->assertRedirect('/login');
    }

    public function test_bang_dieu_khien_chia_duong_theo_vai_tro(): void
    {
        $chuSan = User::factory()->chuSan()->create();

        $this->actingAs(User::factory()->admin()->create())->get('/bang-dieu-khien')->assertRedirect(route('admin.chu-san.index'));
        $this->actingAs($chuSan)->get('/bang-dieu-khien')->assertRedirect(route('quan-ly.tong-quan'));
        $this->actingAs(User::factory()->nhanVienCua($chuSan)->create())->get('/bang-dieu-khien')->assertRedirect(route('quan-ly.tong-quan'));
        $this->actingAs(User::factory()->create())->get('/bang-dieu-khien')->assertRedirect(route('khach.tai-khoan'));
    }

    public function test_khach_khong_vao_duoc_trang_quan_ly_va_admin(): void
    {
        $khach = User::factory()->create();

        $this->actingAs($khach)->get('/quan-ly')->assertForbidden();
        $this->actingAs($khach)->get('/admin/chu-san')->assertForbidden();
        $this->actingAs($khach)->get('/tai-khoan')->assertOk();
    }

    public function test_chu_san_khong_vao_duoc_trang_admin(): void
    {
        $this->actingAs(User::factory()->chuSan()->create())->get('/admin/chu-san')->assertForbidden();
    }

    public function test_nhan_vien_vao_quan_ly_nhung_khong_quan_ly_nhan_vien(): void
    {
        $nv = User::factory()->nhanVienCua(User::factory()->chuSan()->create())->create();

        $this->actingAs($nv)->get('/quan-ly')->assertOk();
        $this->actingAs($nv)->get('/quan-ly/nhan-vien')->assertForbidden();
    }

    public function test_admin_tao_chu_san_kem_ho_so(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/chu-san', [
            'name' => 'Chủ Sân Mới',
            'email' => 'chumoi@example.com',
            'so_dien_thoai' => '0988777666',
            'password' => 'matkhau123',
            'ten_co_so' => 'Sân Bóng Mới',
            'so_ngay_su_dung' => 14,
        ])->assertRedirect(route('admin.chu-san.index'));

        $chu = User::where('email', 'chumoi@example.com')->firstOrFail();
        $this->assertSame(User::CHU_SAN, $chu->vai_tro);
        $this->assertSame('Sân Bóng Mới', $chu->hoSoChuSan->ten_co_so);
        $this->assertTrue($chu->hoSoChuSan->conHan());
    }

    public function test_khoa_api_payos_duoc_luu_ma_hoa(): void
    {
        $chu = User::factory()->chuSan()->create();
        $hoSo = $chu->hoSoChuSan;
        $hoSo->payos_api_key = 'khoa-bi-mat-123';
        $hoSo->save();

        $giaTriTrongCsdl = DB::table('ho_so_chu_san')->where('id', $hoSo->id)->value('payos_api_key');
        $this->assertNotSame('khoa-bi-mat-123', $giaTriTrongCsdl);
        $this->assertSame('khoa-bi-mat-123', $hoSo->fresh()->payos_api_key);
    }
}
