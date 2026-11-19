<?php

namespace Tests\Feature\GiaiDoan8;

use App\Models\KhungGio;
use App\Models\San;
use App\Models\User;
use App\Support\ThoiGian;
use App\Support\Tien;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KhungGioLinhHoatTest extends TestCase
{
    use RefreshDatabase;

    private User $chu;

    private San $san;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chu = User::factory()->chuSan()->create();
        $this->san = San::factory()->cua($this->chu)->create(['gio_mo_cua' => '00:00:00', 'gio_dong_cua' => '24:00:00']);
    }

    public function test_chuan_hoa_tien_va_gio(): void
    {
        $this->assertSame(100000, Tien::so('100.000'));
        $this->assertSame(350000, Tien::so('350.000đ'));
        $this->assertSame(200000, Tien::so('200k'));
        $this->assertSame('06:30', ThoiGian::chuanHoa('6h30'));
        $this->assertSame('22:15', ThoiGian::chuanHoa('22.15'));
        $this->assertNull(ThoiGian::chuanHoa('25:00'));
    }

    public function test_them_khung_go_tu_do_gia_co_dau_cham_va_coc_rieng(): void
    {
        $this->actingAs($this->chu)->post("/quan-ly/san/{$this->san->id}/khung-gio", [
            'loai_ngay' => 'ngay_thuong', 'gio_bat_dau' => '23h', 'gio_ket_thuc' => '24:00', 'gia' => '250.000', 'tien_coc' => '50.000',
        ])->assertSessionHasNoErrors();

        $k = KhungGio::firstOrFail();
        $this->assertSame('23:00–24:00', $k->nhan());
        $this->assertSame(250000, $k->gia);
        $this->assertSame(50000, $k->tienCoc());

        // Cọc lớn hơn giá bị từ chối
        $this->actingAs($this->chu)->post("/quan-ly/san/{$this->san->id}/khung-gio", [
            'loai_ngay' => 'ngay_thuong', 'gio_bat_dau' => '01:00', 'gio_ket_thuc' => '02:00', 'gia' => '100.000', 'tien_coc' => '150.000',
        ])->assertSessionHasErrors('tien_coc');
    }

    public function test_tao_nhanh_nhieu_muc_gia_theo_buoi(): void
    {
        $this->actingAs($this->chu)->post("/quan-ly/san/{$this->san->id}/khung-gio/tao-nhanh", [
            'loai_ngays' => ['ngay_thuong'], 'thoi_luong' => 60,
            'muc' => [
                ['tu' => '05:00', 'den' => '11:00', 'gia' => '200.000'],
                ['tu' => '11:00', 'den' => '15:00', 'gia' => '150.000'],
                ['tu' => '15:00', 'den' => '17:00', 'gia' => '250.000'],
                ['tu' => '17:00', 'den' => '21:00', 'gia' => '400.000', 'vang' => 1],
                ['tu' => '21:00', 'den' => '24:00', 'gia' => ''],               // bỏ trống = không tạo
            ],
        ])->assertSessionHasNoErrors();

        $gia = KhungGio::orderBy('gio_bat_dau')->get()->mapWithKeys(fn ($k) => [substr($k->gio_bat_dau, 0, 5) => $k->gia]);
        $this->assertCount(16, $gia);                       // 05:00 → 21:00
        $this->assertSame(200000, $gia['06:00']);
        $this->assertSame(150000, $gia['12:00']);
        $this->assertSame(250000, $gia['16:00']);
        $this->assertSame(400000, $gia['20:00']);
        $this->assertTrue(KhungGio::where('gio_bat_dau', '18:00:00')->first()->la_gio_vang);
        $this->assertFalse($gia->has('21:00'));
    }

    public function test_luu_tat_ca_sua_gia_coc_va_chan_chong_gio(): void
    {
        $a = $this->san->khungGios()->create(['loai_ngay' => 'ngay_thuong', 'gio_bat_dau' => '17:00', 'gio_ket_thuc' => '18:00', 'gia' => 300000]);
        $b = $this->san->khungGios()->create(['loai_ngay' => 'ngay_thuong', 'gio_bat_dau' => '18:00', 'gio_ket_thuc' => '19:00', 'gia' => 300000]);

        $this->actingAs($this->chu)->put("/quan-ly/san/{$this->san->id}/khung-gio", ['loai_ngay' => 'ngay_thuong', 'khung' => [
            $a->id => ['gio_bat_dau' => '17:00', 'gio_ket_thuc' => '18:00', 'gia' => '350.000', 'tien_coc' => '', 'dang_ap_dung' => 1],
            $b->id => ['gio_bat_dau' => '18:00', 'gio_ket_thuc' => '19:30', 'gia' => '450.000', 'tien_coc' => '100.000', 'la_gio_vang' => 1, 'dang_ap_dung' => 1],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(350000, $a->fresh()->gia);
        $this->assertSame(105000, $a->fresh()->tienCoc());           // để trống → 30%
        $this->assertSame(100000, $b->fresh()->tienCoc());
        $this->assertSame('18:00–19:30', $b->fresh()->nhan());

        // Đổi giờ làm 2 khung chồng nhau → báo lỗi, không lưu gì
        $this->actingAs($this->chu)->put("/quan-ly/san/{$this->san->id}/khung-gio", ['loai_ngay' => 'ngay_thuong', 'khung' => [
            $a->id => ['gio_bat_dau' => '17:00', 'gio_ket_thuc' => '18:30', 'gia' => '1', 'dang_ap_dung' => 1],
        ]])->assertSessionHasErrors('khung');
        $this->assertSame(350000, $a->fresh()->gia);

        // Chủ sân khác không sửa được
        $this->actingAs(User::factory()->chuSan()->create())->put("/quan-ly/san/{$this->san->id}/khung-gio", ['loai_ngay' => 'ngay_thuong', 'khung' => []])
            ->assertForbidden();
        $this->actingAs($this->chu)->get("/quan-ly/san/{$this->san->id}/khung-gio")->assertOk()->assertSee('Lưu tất cả thay đổi');
    }

    public function test_san_mo_xuyen_dem_go_gio_tu_do(): void
    {
        $this->actingAs($this->chu)->get("/quan-ly/san/{$this->san->id}/sua")->assertOk()->assertSee('xuyên đêm');
        $sua = $this->san->only(['ten', 'loai_san', 'xa_phuong_id', 'dia_chi_chi_tiet']);
        $this->actingAs($this->chu)->put("/quan-ly/san/{$this->san->id}", $sua + ['gio_mo_cua' => '5h30', 'gio_dong_cua' => '24:00'])
            ->assertSessionHasNoErrors();
        $this->assertSame('05:30:00', $this->san->fresh()->gio_mo_cua);
        $this->actingAs($this->chu)->put("/quan-ly/san/{$this->san->id}", $sua + ['gio_mo_cua' => '5h10', 'gio_dong_cua' => '24:00'])
            ->assertSessionHasErrors('gio_mo_cua');
    }
}
