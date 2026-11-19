<?php

namespace Tests\Feature\GiaiDoan8;

use App\Models\DatSan;
use App\Models\Doi;
use App\Models\GiaiDau;
use App\Models\GoiThueBao;
use App\Models\TranDau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\GiaiDoan3\CoSanDeDat;
use Tests\TestCase;

class BoSungDatSanTest extends TestCase
{
    use CoSanDeDat;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();
    }

    public function test_da_1h30_tinh_them_50_phan_tram_tu_gio_bat_dau(): void
    {
        $khach = User::factory()->create();
        $this->actingAs($khach)->post('/dat-san', [
            'san_id' => $this->a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->a, '17:00')->id, 'thoi_luong' => 90,
        ])->assertSessionHasNoErrors();

        $don = DatSan::firstOrFail();
        $this->assertSame('17:00–18:30', $don->khungGioNhan());
        $this->assertSame(450000, (int) $don->gia_chot);          // 300.000 × 1,5
        $this->assertSame(135000, (int) $don->tien_coc);          // 30%

        // 1h15 → 375.000
        $this->assertSame(375000, $this->khung($this->a, '20:00')->giaTheoPhut(75));

        // 18:00 bị chiếm nửa đầu → người khác không đặt được
        $this->datOnline(User::factory()->create(), $this->a, '18:00')->assertSessionHasErrors('dat_san');
        // Vượt giờ đóng cửa 23:00 (khung 22:00 đá 90' là quá)
        $this->actingAs(User::factory()->create())->post('/dat-san', [
            'san_id' => $this->a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->a, '22:00')->id, 'thoi_luong' => 90,
        ])->assertSessionHasErrors('dat_san');
    }

    public function test_chu_san_xem_trang_cong_khai_khong_bi_403_va_dat_tai_cho_theo_thoi_luong(): void
    {
        $this->actingAs($this->chu)->get('/san/'.$this->a->slug)->assertOk()->assertSee('chỉ tài khoản', false)->assertDontSee('Đặt · cọc');

        $this->actingAs($this->chu)->post("/quan-ly/san/{$this->a->id}/dat-tai-cho", [
            'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->a, '19:00')->id, 'ten' => 'Anh Ba', 'sdt' => '0911222333', 'thoi_luong' => 120,
        ])->assertSessionHasNoErrors();
        $don = DatSan::firstOrFail();
        $this->assertSame('19:00–21:00', $don->khungGioNhan());
        $this->assertSame(600000, (int) $don->gia_chot);
    }

    public function test_nhan_vien_nhap_ti_so_giai_nhung_khong_tao_giai(): void
    {
        $nv = User::factory()->nhanVienCua($this->chu)->create();
        $g = new GiaiDau;
        $g->forceFill(['chu_san_id' => $this->chu->id, 'ten' => 'Cúp NV', 'slug' => 'cup-nv', 'loai_san' => 5, 'the_thuc' => 'loai_truc_tiep',
            'tu_ngay' => today(), 'den_ngay' => today(), 'so_doi_toi_da' => 4, 'trang_thai' => 'dang_dien_ra'])->save();
        $d1 = Doi::forceCreate(['ten' => 'A', 'slug' => 'a-x', 'doi_truong_id' => User::factory()->create()->id]);
        $d2 = Doi::forceCreate(['ten' => 'B', 'slug' => 'b-x', 'doi_truong_id' => User::factory()->create()->id]);
        $t = TranDau::forceCreate(['giai_dau_id' => $g->id, 'doi_nha_id' => $d1->id, 'doi_khach_id' => $d2->id, 'vong' => 'Chung kết', 'trang_thai' => 'chua_da']);

        $this->actingAs($nv)->get('/quan-ly/giai-dau/cup-nv')->assertOk()->assertSee('nhập tỉ số', false);
        $this->actingAs($nv)->patch("/quan-ly/tran-dau/{$t->id}", ['ban_thang_nha' => 2, 'ban_thang_khach' => 1])->assertSessionHasNoErrors();
        $this->assertSame('da_xong', $t->fresh()->trang_thai);
        $this->actingAs($nv)->get('/quan-ly/giai-dau/tao')->assertForbidden();
        $this->actingAs($nv)->patch('/quan-ly/giai-dau/cup-nv/ket-thuc')->assertForbidden();
        $this->actingAs($this->chu)->get('/giai-dau/cup-nv')->assertSee('Quản lý giải');
    }

    public function test_danh_sach_doi_tim_kiem(): void
    {
        Doi::forceCreate(['ten' => 'FC Nha Trang', 'slug' => 'fc-nt', 'doi_truong_id' => User::factory()->create()->id]);
        Doi::forceCreate(['ten' => 'Sài Gòn United', 'slug' => 'sg-u', 'doi_truong_id' => User::factory()->create()->id]);
        $this->get('/doi?q=nha')->assertOk()->assertSee('FC Nha Trang')->assertDontSee('Sài Gòn United');
    }

    public function test_go_ten_ngan_hang_va_o_tien_co_dau_cham(): void
    {
        $this->actingAs($this->chu)->put('/quan-ly/thanh-toan', [
            'phuong_thuc_tt' => 'vietqr', 'ngan_hang_bin' => 'tp bank', 'so_tai_khoan' => '0384775009', 'ten_chu_tai_khoan' => 'Dang Duy',
            'phut_giu_cho' => 15, 'gio_huy_hoan_coc' => 24,
        ])->assertSessionHasNoErrors();
        $this->assertSame('970423', $this->chu->hoSoChuSan->fresh()->ngan_hang_bin);

        $this->actingAs($this->chu)->put('/quan-ly/thanh-toan', [
            'phuong_thuc_tt' => 'vietqr', 'ngan_hang_bin' => 'Ngân hàng không có', 'so_tai_khoan' => '0384775009', 'ten_chu_tai_khoan' => 'Dang Duy',
            'phut_giu_cho' => 15, 'gio_huy_hoan_coc' => 24,
        ])->assertSessionHasErrors('ngan_hang_bin');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/goi', ['ten' => 'Gói Lớn', 'gia_thang' => '1.200.000', 'so_san_toi_da' => 9, 'so_nhan_vien_toi_da' => 9, 'dang_ban' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(1200000, (int) GoiThueBao::where('ten', 'Gói Lớn')->value('gia_thang'));
    }
}
