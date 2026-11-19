<?php

namespace Tests\Feature\GiaiDoan2;

use App\Models\KhungGio;
use App\Models\NgayLe;
use App\Models\San;
use App\Models\User;
use App\Services\BangGia;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KhungGioTest extends TestCase
{
    use RefreshDatabase;

    private User $chu;
    private San $san;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chu = User::factory()->chuSan()->create();
        $this->san = San::factory()->cua($this->chu)->create(['gio_mo_cua' => '06:00:00', 'gio_dong_cua' => '23:00:00']);
    }

    private function them(array $d)
    {
        return $this->actingAs($this->chu)->post("/quan-ly/san/{$this->san->id}/khung-gio",
            $d + ['loai_ngay' => 'ngay_thuong', 'gia' => 300000]);
    }

    public function test_them_khung_gio_hop_le(): void
    {
        $this->them(['gio_bat_dau' => '17:00', 'gio_ket_thuc' => '18:30', 'la_gio_vang' => 1])->assertSessionHasNoErrors();

        $k = KhungGio::firstOrFail();
        $this->assertSame('17:00:00', $k->gio_bat_dau);
        $this->assertTrue($k->la_gio_vang);
        $this->assertSame(90000, $k->tienCoc()); // 30% của 300.000
    }

    public function test_chan_khung_chong_gio_va_gio_le_phut(): void
    {
        $this->them(['gio_bat_dau' => '17:00', 'gio_ket_thuc' => '18:30']);

        $this->them(['gio_bat_dau' => '18:00', 'gio_ket_thuc' => '19:00'])->assertSessionHasErrors('gio_bat_dau');   // chồng
        $this->them(['gio_bat_dau' => '19:10', 'gio_ket_thuc' => '20:00'])->assertSessionHasErrors('gio_bat_dau');   // lẻ phút
        $this->them(['gio_bat_dau' => '22:00', 'gio_ket_thuc' => '23:30'])->assertSessionHasErrors('gio_bat_dau');   // quá giờ đóng
        $this->them(['gio_bat_dau' => '20:00', 'gio_ket_thuc' => '19:00'])->assertSessionHasErrors('gio_ket_thuc');  // ngược

        // Cùng giờ nhưng khác loại ngày thì được
        $this->them(['gio_bat_dau' => '17:00', 'gio_ket_thuc' => '18:30', 'loai_ngay' => 'cuoi_tuan'])->assertSessionHasNoErrors();
        $this->assertSame(2, KhungGio::count());
    }

    public function test_sua_khung_khong_bi_bao_chong_voi_chinh_no(): void
    {
        $this->them(['gio_bat_dau' => '17:00', 'gio_ket_thuc' => '18:30']);
        $k = KhungGio::firstOrFail();

        $this->actingAs($this->chu)->put("/quan-ly/khung-gio/{$k->id}", [
            'loai_ngay' => 'ngay_thuong', 'gio_bat_dau' => '17:00', 'gio_ket_thuc' => '18:30', 'gia' => 350000, 'dang_ap_dung' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame(350000, $k->fresh()->gia);
        $this->assertFalse($k->fresh()->dang_ap_dung);
    }

    public function test_tao_nhanh_ca_ngay_co_gio_vang(): void
    {
        $this->actingAs($this->chu)->post("/quan-ly/san/{$this->san->id}/khung-gio/tao-nhanh", [
            'loai_ngays' => ['ngay_thuong', 'cuoi_tuan'], 'thoi_luong' => 90, 'gia' => 300000,
            'gio_vang_tu' => '17:00', 'gio_vang_den' => '21:00', 'gia_vang' => 450000,
        ])->assertSessionHasNoErrors();

        // 06:00 → 23:00, mỗi khung 90' → 11 khung (khung cuối 21:00–22:30)
        $thuong = $this->san->khungGios()->where('loai_ngay', 'ngay_thuong')->get();
        $this->assertCount(11, $thuong);
        $this->assertSame('21:00:00', $thuong->last()->gio_bat_dau);

        $vang = $thuong->where('la_gio_vang', true);
        $this->assertSame(['18:00:00', '19:30:00'], $vang->pluck('gio_bat_dau')->values()->all());
        $this->assertTrue($vang->every(fn ($k) => $k->gia === 450000));
        $this->assertSame(22, KhungGio::count());

        // Chạy lại không tạo trùng
        $this->actingAs($this->chu)->post("/quan-ly/san/{$this->san->id}/khung-gio/tao-nhanh", [
            'loai_ngays' => ['ngay_thuong'], 'thoi_luong' => 90, 'gia' => 300000,
        ]);
        $this->assertSame(22, KhungGio::count());
    }

    public function test_khong_sua_khung_gio_san_nguoi_khac(): void
    {
        $chuB = User::factory()->chuSan()->create();
        $this->them(['gio_bat_dau' => '17:00', 'gio_ket_thuc' => '18:30']);
        $k = KhungGio::firstOrFail();

        $this->actingAs($chuB)->post("/quan-ly/san/{$this->san->id}/khung-gio", [
            'loai_ngay' => 'ngay_thuong', 'gio_bat_dau' => '06:00', 'gio_ket_thuc' => '07:00', 'gia' => 1,
        ])->assertForbidden();
        $this->actingAs($chuB)->put("/quan-ly/khung-gio/{$k->id}", [
            'loai_ngay' => 'ngay_thuong', 'gio_bat_dau' => '17:00', 'gio_ket_thuc' => '18:30', 'gia' => 1,
        ])->assertForbidden();
        $this->actingAs($chuB)->delete("/quan-ly/khung-gio/{$k->id}")->assertForbidden();

        $this->assertSame(300000, $k->fresh()->gia);
    }

    public function test_bang_gia_chon_dung_loai_ngay(): void
    {
        $bg = app(BangGia::class);
        $bg->taoNhanh($this->san, ['ngay_thuong'], 60, 200000);
        $bg->taoNhanh($this->san, ['cuoi_tuan'], 60, 300000);

        $thu4 = Carbon::parse('2026-11-18');   // thứ Tư
        $chuNhat = Carbon::parse('2026-11-22');
        $leChung = Carbon::parse('2026-12-24'); // thứ Năm
        $leRieng = Carbon::parse('2026-11-19'); // thứ Năm
        NgayLe::create(['ngay' => '2026-12-24', 'ten' => 'Giáng sinh']);
        $le = new NgayLe(['ngay' => '2026-11-19', 'ten' => 'Khai trương']);
        $le->chu_san_id = $this->chu->id;
        $le->save();

        $this->assertSame('ngay_thuong', $bg->loaiNgay($this->san, $thu4));
        $this->assertSame('cuoi_tuan', $bg->loaiNgay($this->san, $chuNhat));
        $this->assertSame('ngay_le', $bg->loaiNgay($this->san, $leChung));
        $this->assertSame('ngay_le', $bg->loaiNgay($this->san, $leRieng));

        // Ngày lễ riêng của chủ A không ảnh hưởng sân chủ khác
        $sanKhac = San::factory()->create();
        $this->assertSame('ngay_thuong', $bg->loaiNgay($sanKhac, $leRieng));

        // Chưa có giá lễ → dùng giá cuối tuần
        $this->assertSame(300000, $bg->khungGioTrongNgay($this->san, $leChung)->first()->gia);
        $this->assertSame(200000, $bg->khungGioTrongNgay($this->san, $thu4)->first()->gia);
    }

    public function test_chu_san_them_va_xoa_ngay_le_rieng(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 10:00'));

        $this->actingAs($this->chu)->post('/quan-ly/ngay-le', ['ngay' => '2026-11-20', 'ten' => 'Khai trương'])
            ->assertSessionHasNoErrors();
        $le = NgayLe::firstOrFail();
        $this->assertSame($this->chu->id, $le->chu_san_id);

        $chuB = User::factory()->chuSan()->create();
        $this->actingAs($chuB)->delete("/quan-ly/ngay-le/{$le->id}")->assertForbidden();

        $chung = NgayLe::create(['ngay' => '2026-12-24', 'ten' => 'Giáng sinh']);
        $this->actingAs($this->chu)->delete("/quan-ly/ngay-le/{$chung->id}")->assertForbidden();
    }
}
