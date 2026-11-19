<?php

namespace Tests\Feature\GiaiDoan3;

use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\User;
use App\Services\DatSanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DatSanTest extends TestCase
{
    use RefreshDatabase, CoSanDeDat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();
    }

    public function test_khach_giu_cho_va_gia_duoc_chot(): void
    {
        $khach = User::factory()->create();

        $this->datOnline($khach, $this->a)->assertRedirect(route('dat-san.thanh-toan', DatSan::first()));

        $don = DatSan::firstOrFail();
        $this->assertSame('cho_thanh_toan', $don->trang_thai);
        $this->assertSame('DS'.str_pad((string) $don->id, 6, '0', STR_PAD_LEFT), $don->ma_don);
        $this->assertSame(300000, $don->gia_chot);
        $this->assertSame(90000, $don->tien_coc);                        // 30%
        $this->assertSame('2026-11-18 08:15:00', $don->giu_cho_den->format('Y-m-d H:i:s'));
        $this->assertSame(4, DB::table('o_lich')->where('san_id', $this->a->id)->count()); // 60' = 4 ô 15'

        $gd = GiaoDich::firstOrFail();
        $this->assertSame($don->ma_don, $gd->noi_dung_ck);
        $this->assertSame((int) $this->chu->id, (int) $gd->chu_san_id);   // tiền về chủ sân

        // Chủ sân đổi giá sau đó → đơn cũ không đổi
        $this->khung($this->a)->update(['gia' => 999000]);
        $this->assertSame(300000, $don->fresh()->gia_chot);
    }

    public function test_hai_nguoi_dat_cung_o_chi_mot_nguoi_duoc(): void
    {
        $this->datOnline(User::factory()->create(), $this->a)->assertSessionHasNoErrors();
        $this->datOnline(User::factory()->create(), $this->a)->assertSessionHasErrors('dat_san');

        $this->assertSame(1, DatSan::count());
    }

    public function test_csdl_tu_chan_trung_ke_ca_khi_bo_qua_kiem_tra_code(): void
    {
        $dv = app(DatSanService::class);
        $dv->ghiDon($this->a, '2026-11-18', 17 * 60, 18 * 60, ['gia_chot' => 1, 'tien_coc' => 0, 'trang_thai' => 'da_dat', 'nguon' => 'tai_cho']);

        $this->expectException(ValidationException::class);
        // Khung lệch giờ (17:30–18:30) vẫn trùng ô 17:30–18:00 → UNIQUE(san_id, ngay, o) chặn
        $dv->ghiDon($this->a, '2026-11-18', 17 * 60 + 30, 18 * 60 + 30, ['gia_chot' => 1, 'tien_coc' => 0, 'trang_thai' => 'da_dat', 'nguon' => 'tai_cho']);
    }

    public function test_dat_san_lon_khoa_san_con_va_nguoc_lai(): void
    {
        $this->datOnline(User::factory()->create(), $this->bay, '17:00')->assertSessionHasNoErrors();

        // sân con cùng giờ bị khoá
        $this->datOnline(User::factory()->create(), $this->a, '17:00')->assertSessionHasErrors('dat_san');
        $this->datOnline(User::factory()->create(), $this->b, '17:00')->assertSessionHasErrors('dat_san');
        // giờ khác vẫn đặt được
        $this->datOnline(User::factory()->create(), $this->a, '18:00')->assertSessionHasNoErrors();
        // sân con đã đặt 18:00 → sân lớn 18:00 bị khoá
        $this->datOnline(User::factory()->create(), $this->bay, '18:00')->assertSessionHasErrors('dat_san');
    }

    public function test_hai_san_con_doc_lap_voi_nhau(): void
    {
        $this->datOnline(User::factory()->create(), $this->a, '17:00')->assertSessionHasNoErrors();
        $this->datOnline(User::factory()->create(), $this->b, '17:00')->assertSessionHasNoErrors();
    }

    public function test_khong_dat_gio_da_qua_hoac_qua_xa(): void
    {
        $k = User::factory()->create();
        $this->datOnline($k, $this->a, '07:00')->assertSessionHasErrors('dat_san');                 // 07:00 < 08:00 hiện tại
        $this->datOnline($k, $this->a, '17:00', '2027-01-30')->assertSessionHasErrors('dat_san');   // quá 30 ngày
    }

    public function test_khung_cua_san_khac_khong_dung_duoc(): void
    {
        $this->actingAs(User::factory()->create())->post('/dat-san', [
            'san_id' => $this->a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->b)->id,
        ])->assertSessionHasErrors('dat_san');
    }

    public function test_gioi_han_so_o_dang_giu(): void
    {
        $k = User::factory()->create();
        $this->datOnline($k, $this->a, '17:00')->assertSessionHasNoErrors();
        $this->datOnline($k, $this->a, '18:00')->assertSessionHasNoErrors();
        $this->datOnline($k, $this->a, '19:00')->assertSessionHasErrors('dat_san');
    }

    public function test_chu_san_chua_cai_tai_khoan_thi_khong_dat_online(): void
    {
        $this->chu->hoSoChuSan->forceFill(['so_tai_khoan' => null])->save();
        $this->datOnline(User::factory()->create(), $this->a)->assertSessionHasErrors('dat_san');
    }

    public function test_chu_san_het_han_hoac_san_tam_ngung(): void
    {
        $this->a->forceFill(['trang_thai' => 'tam_ngung'])->save();
        $this->datOnline(User::factory()->create(), $this->a)->assertSessionHasErrors('dat_san');
        $this->get('/san/'.$this->a->slug)->assertNotFound();

        $this->chu->hoSoChuSan->forceFill(['het_han_luc' => now()->subDay()])->save();
        $this->datOnline(User::factory()->create(), $this->b)->assertSessionHasErrors('dat_san');
    }

    public function test_chi_khach_moi_dat_online_duoc(): void
    {
        $this->actingAs($this->chu)->post('/dat-san', [
            'san_id' => $this->a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->a)->id,
        ])->assertForbidden();
        $this->post('/logout');
        $this->post('/dat-san', [])->assertRedirect('/login');
    }

    public function test_trang_thanh_toan_co_qr_va_chi_chu_don_xem_duoc(): void
    {
        $khach = User::factory()->create();
        $this->datOnline($khach, $this->a);
        $don = DatSan::firstOrFail();

        $this->actingAs($khach)->get("/dat-san/{$don->ma_don}/thanh-toan")
            ->assertOk()->assertSee('<svg', false)->assertSee($don->ma_don)->assertSee('NGUYEN VAN CHU');
        $this->actingAs(User::factory()->create())->get("/dat-san/{$don->ma_don}/thanh-toan")->assertForbidden();
        $this->actingAs($khach)->getJson("/dat-san/{$don->ma_don}/trang-thai")->assertJsonPath('trang_thai', 'cho_thanh_toan');
    }
}
