<?php

namespace Tests\Feature\GiaiDoan3;

use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\User;
use App\Notifications\DatSanThanhCong;
use App\Notifications\NhacLichDa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class XacNhanVaHuyTest extends TestCase
{
    use RefreshDatabase, CoSanDeDat;

    private User $khach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();
        $this->khach = User::factory()->create();
    }

    private function donCho(string $gio = '17:00', string $ngay = '2026-11-18'): DatSan
    {
        $this->datOnline($this->khach, $this->a, $gio, $ngay)->assertSessionHasNoErrors();

        return DatSan::latest('id')->firstOrFail();
    }

    public function test_nhan_vien_xac_nhan_coc_va_khach_nhan_email(): void
    {
        Notification::fake();
        $nv = User::factory()->nhanVienCua($this->chu)->create();
        $don = $this->donCho();

        $this->actingAs($nv)->patch("/quan-ly/dat-san/{$don->ma_don}/xac-nhan-coc")->assertSessionHasNoErrors();

        $don->refresh();
        $this->assertSame('da_dat', $don->trang_thai);
        $this->assertSame($nv->id, (int) $don->xac_nhan_coc_boi_id);
        $this->assertSame('thanh_cong', GiaoDich::first()->trang_thai);
        Notification::assertSentTo($this->khach, DatSanThanhCong::class);

        // Bấm lần 2 không ghi đôi
        $this->actingAs($nv)->patch("/quan-ly/dat-san/{$don->ma_don}/xac-nhan-coc")->assertSessionHasErrors('dat_san');
    }

    public function test_chu_san_khac_khong_xac_nhan_duoc(): void
    {
        $don = $this->donCho();
        $chuKhac = User::factory()->chuSan()->create();

        $this->actingAs($chuKhac)->patch("/quan-ly/dat-san/{$don->ma_don}/xac-nhan-coc")->assertForbidden();
        $this->actingAs($chuKhac)->patch("/quan-ly/dat-san/{$don->ma_don}/huy")->assertForbidden();
        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai);
    }

    public function test_het_15_phut_tu_nha_cho_va_nguoi_khac_dat_duoc(): void
    {
        $don = $this->donCho();

        $this->travel(16)->minutes();
        $this->artisan('dat-san:nha-cho')->assertSuccessful();

        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertSame(0, DB::table('o_lich')->where('dat_san_id', $don->id)->count());
        $this->assertSame('het_han', GiaoDich::first()->trang_thai);

        $this->datOnline(User::factory()->create(), $this->a, '17:00')->assertSessionHasNoErrors();
    }

    public function test_gia_han_giu_cho(): void
    {
        $don = $this->donCho();
        $this->travel(10)->minutes();

        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/gia-han")->assertSessionHasNoErrors();
        $this->travel(10)->minutes(); // 20' sau khi giữ, quá 15' gốc nhưng đã gia hạn
        $this->artisan('dat-san:nha-cho');

        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai);
    }

    public function test_khach_huy_som_duoc_hoan_coc(): void
    {
        $don = $this->donCho('17:00', '2026-11-20');                       // còn > 24h
        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/xac-nhan-coc");

        $this->actingAs($this->khach)->post("/dat-san/{$don->ma_don}/huy")->assertRedirect();

        $don->refresh();
        $this->assertSame('da_huy', $don->trang_thai);
        $this->assertSame('cho_hoan', $don->xu_ly_coc);
        $this->assertSame(0, DB::table('o_lich')->where('dat_san_id', $don->id)->count());

        // Chủ sân chuyển trả rồi bấm "Đã hoàn"
        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/da-hoan")->assertSessionHasNoErrors();
        $this->assertSame('da_hoan', $don->fresh()->xu_ly_coc);
    }

    public function test_khach_huy_sat_gio_mat_coc(): void
    {
        $don = $this->donCho('17:00', '2026-11-18');                       // cùng ngày → < 24h
        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/xac-nhan-coc");

        $this->actingAs($this->khach)->post("/dat-san/{$don->ma_don}/huy");

        $this->assertSame('mat_coc', $don->fresh()->xu_ly_coc);
    }

    public function test_chu_san_huy_thi_phai_hoan_nhan_vien_khong_huy_duoc(): void
    {
        $don = $this->donCho();
        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/xac-nhan-coc");
        $nv = User::factory()->nhanVienCua($this->chu)->create();

        $this->actingAs($nv)->patch("/quan-ly/dat-san/{$don->ma_don}/huy")->assertForbidden();
        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/huy", ['ly_do' => 'Mưa ngập'])->assertSessionHasNoErrors();

        $this->assertSame('cho_hoan', $don->fresh()->xu_ly_coc);
    }

    public function test_khach_khac_khong_huy_duoc_don_nguoi_khac(): void
    {
        $don = $this->donCho();
        $this->actingAs(User::factory()->create())->post("/dat-san/{$don->ma_don}/huy")->assertForbidden();
    }

    public function test_dat_tai_cho_va_gioi_han_quyen(): void
    {
        $nv = User::factory()->nhanVienCua($this->chu)->create();

        $this->actingAs($nv)->post("/quan-ly/san/{$this->a->id}/dat-tai-cho", [
            'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->a, '19:00')->id,
            'ten' => 'Anh Tuấn', 'sdt' => '0912 345 678', 'da_thu_coc' => 1,
        ])->assertSessionHasNoErrors();

        $don = DatSan::firstOrFail();
        $this->assertSame('da_dat', $don->trang_thai);
        $this->assertSame('tai_cho', $don->nguon);
        $this->assertSame('0912345678', $don->sdt_khach_tai_cho);
        $this->assertSame(90000, $don->tien_coc);
        $this->assertSame('tien_mat', GiaoDich::first()->phuong_thuc);

        // Khách online không đặt được giờ đó nữa
        $this->datOnline($this->khach, $this->a, '19:00')->assertSessionHasErrors('dat_san');

        // Nhân viên chủ khác không đặt hộ được
        $nvKhac = User::factory()->nhanVienCua(User::factory()->chuSan()->create())->create();
        $this->actingAs($nvKhac)->post("/quan-ly/san/{$this->a->id}/dat-tai-cho", [
            'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->a, '20:00')->id, 'ten' => 'X', 'sdt' => '0912345679',
        ])->assertForbidden();
    }

    public function test_nhac_lich_truoc_gio_da_mot_lan(): void
    {
        Notification::fake();
        $don = $this->donCho('17:00');
        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/xac-nhan-coc");

        $this->travelTo(now()->setTime(15, 30));
        $this->artisan('dat-san:nhac-lich');
        $this->artisan('dat-san:nhac-lich');

        Notification::assertSentToTimes($this->khach, NhacLichDa::class, 1);
    }
}
