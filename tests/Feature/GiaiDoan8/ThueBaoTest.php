<?php

namespace Tests\Feature\GiaiDoan8;

use App\Models\CaiDat;
use App\Models\GoiThueBao;
use App\Models\San;
use App\Models\ThueBao;
use App\Models\User;
use App\Notifications\GoiSapHetHan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ThueBaoTest extends TestCase
{
    use RefreshDatabase;

    private GoiThueBao $goi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->goi = GoiThueBao::create(['ten' => 'Cơ bản', 'gia_thang' => 199000, 'so_san_toi_da' => 2, 'so_nhan_vien_toi_da' => 1]);
        CaiDat::dat(['tk_bin' => '970436', 'tk_so' => '0011223344', 'tk_ten' => 'ADMIN']);
    }

    private function chuHetHan(): User
    {
        $chu = User::factory()->chuSan()->create();
        $chu->hoSoChuSan->forceFill(['het_han_luc' => now()->subDay(), 'goi_thue_bao_id' => $this->goi->id])->save();

        return $chu;
    }

    public function test_chu_san_mua_goi_admin_xac_nhan_thi_cong_noi_tiep_han(): void
    {
        $chu = User::factory()->chuSan()->create();               // còn 30 ngày
        $hanCu = $chu->hoSoChuSan->het_han_luc->copy();

        $this->actingAs($chu)->post('/quan-ly/goi', ['goi_thue_bao_id' => $this->goi->id, 'so_thang' => 3])->assertRedirect();
        $tb = ThueBao::firstOrFail();
        $this->assertSame(597000, (int) $tb->so_tien);
        $this->actingAs($chu)->get("/quan-ly/goi/don/{$tb->id}")->assertOk()->assertSee('THUEBAO '.$tb->id)->assertSee('<svg', false);

        // Chủ sân khác không xem được đơn này; khách không vào được trang admin
        $this->actingAs(User::factory()->chuSan()->create())->get("/quan-ly/goi/don/{$tb->id}")->assertForbidden();
        $this->actingAs(User::factory()->create())->patch("/admin/thue-bao/{$tb->id}/xac-nhan")->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch("/admin/thue-bao/{$tb->id}/xac-nhan")->assertSessionHasNoErrors();

        $hoSo = $chu->hoSoChuSan->fresh();
        $this->assertSame($this->goi->id, $hoSo->goi_thue_bao_id);
        $this->assertTrue($hoSo->het_han_luc->equalTo($hanCu->copy()->addMonthsNoOverflow(3)));
        $this->assertSame('da_thanh_toan', $tb->fresh()->trang_thai);

        // Xác nhận 2 lần không cộng thêm
        $this->actingAs($admin)->patch("/admin/thue-bao/{$tb->id}/xac-nhan")->assertSessionHasErrors('thue_bao');
        $this->actingAs($admin)->get('/admin/thue-bao?trang_thai=da_thanh_toan')->assertOk()->assertSee('597.000đ');
    }

    public function test_tao_don_moi_thi_don_cho_cu_tu_huy(): void
    {
        $chu = User::factory()->chuSan()->create();
        $this->actingAs($chu)->post('/quan-ly/goi', ['goi_thue_bao_id' => $this->goi->id, 'so_thang' => 1]);
        $this->actingAs($chu)->post('/quan-ly/goi', ['goi_thue_bao_id' => $this->goi->id, 'so_thang' => 12]);
        $this->assertSame(1, ThueBao::where('trang_thai', 'cho_thanh_toan')->count());
        $this->assertSame(1, ThueBao::where('trang_thai', 'da_huy')->count());

        $this->actingAs($chu)->post('/quan-ly/goi', ['goi_thue_bao_id' => $this->goi->id, 'so_thang' => 5])->assertSessionHasErrors('thue_bao');
    }

    public function test_het_han_khoa_khu_quan_ly_an_san_va_mo_lai_khi_gia_han(): void
    {
        $chu = $this->chuHetHan();
        San::factory()->cua($chu)->create(['ten' => 'Sân Hết Hạn Test']);
        $nv = User::factory()->nhanVienCua($chu)->create();

        $this->actingAs($chu)->get('/quan-ly/san')->assertRedirect('/quan-ly/goi');
        $this->actingAs($chu)->get('/quan-ly/goi')->assertOk();
        $this->actingAs($chu)->get('/quan-ly')->assertOk()->assertSee('ĐÃ HẾT HẠN');
        $this->actingAs($nv)->get('/quan-ly/san')->assertRedirect('/quan-ly');
        $this->get('/')->assertDontSee('Sân Hết Hạn Test');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch("/admin/chu-san/{$chu->id}/gia-han", ['so_ngay' => 10, 'so_tien' => 70000])->assertSessionHasNoErrors();

        $this->assertTrue($chu->hoSoChuSan->fresh()->het_han_luc->between(now()->addDays(9), now()->addDays(11)));
        $chu = $chu->fresh(); // bỏ hồ sơ cũ (hết hạn) đang nhớ trong đối tượng test; web thật mỗi request tự đọc lại
        $this->actingAs($chu)->get('/quan-ly/san')->assertOk();
        $this->get('/')->assertSee('Sân Hết Hạn Test');
        $this->assertSame(70000, (int) ThueBao::where('trang_thai', 'da_thanh_toan')->sum('so_tien'));
    }

    public function test_thoi_gian_mien_phi_khong_khoa_khong_gioi_han(): void
    {
        $chu = $this->chuHetHan();                                   // gói Cơ bản: 2 sân, 1 nhân viên
        San::factory()->cua($chu)->count(2)->create();
        User::factory()->nhanVienCua($chu)->create();

        // Hết miễn phí: bị chặn
        $this->actingAs($chu)->get('/quan-ly/san')->assertRedirect('/quan-ly/goi');

        // Admin bật miễn phí
        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/cai-dat', ['mien_phi_den' => today()->addMonth()->toDateString(), 'tk_bin' => '970436', 'tk_so' => '0011223344', 'tk_ten' => 'admin'])
            ->assertSessionHasNoErrors();
        $this->assertSame('ADMIN', CaiDat::lay('tk_ten'));
        $this->assertTrue(CaiDat::dangMienPhi());

        $this->actingAs($chu)->get('/quan-ly/san')->assertOk();
        $this->actingAs($chu)->get('/quan-ly/goi')->assertSee('miễn phí');
        // Không giới hạn nhân viên trong thời gian miễn phí
        $this->actingAs($chu)->post('/quan-ly/nhan-vien', [
            'name' => 'NV2', 'email' => 'nv2@vd.vn', 'so_dien_thoai' => '0987000111', 'password' => 'matkhau123',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $chu->nhanViens()->count());
    }

    public function test_het_mien_phi_thi_gioi_han_nhan_vien_theo_goi(): void
    {
        $chu = User::factory()->chuSan()->create();
        $chu->hoSoChuSan->forceFill(['goi_thue_bao_id' => $this->goi->id])->save();
        User::factory()->nhanVienCua($chu)->create();

        $this->actingAs($chu)->post('/quan-ly/nhan-vien', [
            'name' => 'NV2', 'email' => 'nv2@vd.vn', 'so_dien_thoai' => '0987000111', 'password' => 'matkhau123',
        ])->assertSessionHasErrors('goi');
        $this->assertSame(1, $chu->nhanViens()->count());
    }

    public function test_nhac_het_han_gui_mot_lan(): void
    {
        Notification::fake();
        $chu = User::factory()->chuSan()->create();
        $chu->hoSoChuSan->forceFill(['het_han_luc' => now()->addDays(2)])->save();
        $hetRoi = $this->chuHetHan();

        $this->artisan('thue-bao:nhac')->assertSuccessful();
        $this->artisan('thue-bao:nhac')->assertSuccessful();

        Notification::assertSentToTimes($chu, GoiSapHetHan::class, 1);
        Notification::assertNotSentTo($hetRoi, GoiSapHetHan::class);
        $this->assertTrue($hetRoi->hoSoChuSan->fresh()->bi_khoa_do_het_han);
    }

    public function test_admin_quan_ly_goi(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/goi', [
            'ten' => 'Doanh nghiệp', 'gia_thang' => 1500000, 'so_san_toi_da' => 50, 'so_nhan_vien_toi_da' => 20, 'dang_ban' => 1,
        ])->assertSessionHasNoErrors();
        $g = GoiThueBao::where('ten', 'Doanh nghiệp')->firstOrFail();

        $this->actingAs($admin)->put("/admin/goi/{$g->id}", [
            'ten' => 'Doanh nghiệp', 'gia_thang' => 1200000, 'so_san_toi_da' => 50, 'so_nhan_vien_toi_da' => 20,
        ])->assertSessionHasNoErrors();
        $this->assertFalse($g->fresh()->dang_ban);
        $this->assertSame(1200000, (int) $g->fresh()->gia_thang);

        // Gói ngừng bán không mua được
        $chu = User::factory()->chuSan()->create();
        $this->actingAs($chu)->post('/quan-ly/goi', ['goi_thue_bao_id' => $g->id, 'so_thang' => 1])->assertSessionHasErrors('thue_bao');
        $this->actingAs($chu)->get('/admin/goi')->assertForbidden();
        $this->actingAs($admin)->get('/admin/goi')->assertOk()->assertSee('Doanh nghiệp');
        $this->actingAs($admin)->get('/admin/cai-dat')->assertOk();
    }
}
