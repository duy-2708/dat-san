<?php

namespace Tests\Feature\GiaiDoan10;

use App\Models\CoSo;
use App\Models\DatSan;
use App\Models\KhungGio;
use App\Models\San;
use App\Models\User;
use App\Models\XaPhuong;
use App\Services\DatSanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoSoTest extends TestCase
{
    use RefreshDatabase;

    private User $chu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-18 08:00:00'));
        $this->chu = User::factory()->chuSan()->create();
        $this->chu->hoSoChuSan->fill(['ngan_hang_bin' => '970436', 'so_tai_khoan' => '0123456789', 'ten_chu_tai_khoan' => 'NGUYEN VAN CHU'])->save();
    }

    private function taoCoSo(int $soLuong = 3): CoSo
    {
        $this->actingAs($this->chu)->post('/quan-ly/san', [
            'ten_co_so' => 'Sân Mỹ Anh', 'ten' => 'Sân 5', 'loai_san' => 5, 'so_luong' => $soLuong,
            'xa_phuong_id' => XaPhuong::factory()->create()->id, 'dia_chi_chi_tiet' => '12 Lê Lợi',
            'gio_mo_cua' => '06:00', 'gio_dong_cua' => '23:00',
        ])->assertSessionHasNoErrors();

        return CoSo::where('ten', 'Sân Mỹ Anh')->firstOrFail();
    }

    public function test_tao_mot_lan_nhieu_san_cung_co_so(): void
    {
        $cs = $this->taoCoSo();
        $this->assertSame(['Sân 5A', 'Sân 5B', 'Sân 5C'], $cs->sans()->pluck('ten')->all());

        $this->actingAs($this->chu)->post('/quan-ly/san', ['co_so_id' => $cs->id, 'ten' => 'Sân 7A', 'loai_san' => 7])->assertSessionHasNoErrors();
        $bay = San::where('ten', 'Sân 7A')->firstOrFail();
        $this->assertSame($cs->id, $bay->co_so_id);
        $this->assertSame('12 Lê Lợi', $bay->dia_chi_chi_tiet);

        $this->actingAs($this->chu)->get('/quan-ly/san')->assertOk()->assertSee('Sân Mỹ Anh')->assertSee('3 sân 5');
    }

    public function test_san_cung_loai_dung_chung_bang_gia_tru_gia_rieng(): void
    {
        $cs = $this->taoCoSo();
        [$a, $b, $c] = $cs->sans()->get()->all();

        $this->actingAs($this->chu)->post("/quan-ly/san/{$a->id}/khung-gio/tao-nhanh", [
            'loai_ngays' => ['ngay_thuong'], 'thoi_luong' => 60, 'gia' => 300000,
        ])->assertSessionHasNoErrors();
        $this->assertSame(17, $b->khungGios()->count());
        $this->assertSame(17, $c->khungGios()->count());

        $c->forceFill(['gia_rieng' => true])->save();
        $k = $a->khungGios()->where('gio_bat_dau', '17:00:00')->first();
        $this->actingAs($this->chu)->put("/quan-ly/khung-gio/{$k->id}", [
            'loai_ngay' => 'ngay_thuong', 'gio_bat_dau' => '17:00', 'gio_ket_thuc' => '18:00', 'gia' => '450.000',
        ])->assertSessionHasNoErrors();

        $this->assertSame(450000, $b->khungGios()->where('gio_bat_dau', '17:00:00')->value('gia') + 0);
        $this->assertSame(300000, $c->khungGios()->where('gio_bat_dau', '17:00:00')->value('gia') + 0);
    }

    public function test_sua_dia_chi_ap_dung_ca_co_so(): void
    {
        $cs = $this->taoCoSo(2);
        [$a, $b] = $cs->sans()->get()->all();
        $this->actingAs($this->chu)->put("/quan-ly/san/{$a->id}", [
            'ten_co_so' => 'Sân Mỹ Anh Mới', 'ten' => 'Sân 5A', 'loai_san' => 5, 'xa_phuong_id' => $a->xa_phuong_id,
            'dia_chi_chi_tiet' => '99 Hai Bà Trưng', 'gio_mo_cua' => '05:00', 'gio_dong_cua' => '23:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame('99 Hai Bà Trưng', $b->fresh()->dia_chi_chi_tiet);
        $this->assertSame('Sân Mỹ Anh Mới', $cs->fresh()->ten);
    }

    public function test_khach_dat_san_bat_ky_he_thong_tu_xep(): void
    {
        $cs = $this->taoCoSo(2);
        [$a, $b] = $cs->sans()->get()->all();
        $this->actingAs($this->chu)->post("/quan-ly/san/{$a->id}/khung-gio/tao-nhanh", ['loai_ngays' => ['ngay_thuong'], 'thoi_luong' => 60, 'gia' => 300000]);
        $kA = $a->khungGios()->where('gio_bat_dau', '17:00:00')->firstOrFail();
        app(DatSanService::class)->ghiDon($a, '2026-11-18', 17 * 60, 18 * 60, [
            'ten_khach_tai_cho' => 'Khách cũ', 'sdt_khach_tai_cho' => '0988777666', 'khung_gio_id' => $kA->id,
            'gia_chot' => 300000, 'tien_coc' => 0, 'nguon' => 'tai_cho', 'trang_thai' => 'da_dat',
        ]);

        $this->get("/san/{$a->slug}")->assertOk()->assertSee('Sân 5 bất kỳ')->assertSee('Còn 1/2 sân');

        $khach = User::factory()->create();
        $this->actingAs($khach)->post('/dat-san', ['san_id' => $a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $kA->id, 'tu_xep' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame($b->id, DatSan::where('khach_id', $khach->id)->value('san_id'));

        $this->actingAs(User::factory()->create())->post('/dat-san', ['san_id' => $a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $kA->id, 'tu_xep' => 1])
            ->assertSessionHasErrors();
    }

    public function test_trang_chu_gop_theo_co_so(): void
    {
        $this->taoCoSo(3);
        $this->get('/')->assertOk()->assertSee('Sân Mỹ Anh')->assertSee('Sân 5A, Sân 5B, Sân 5C')->assertSee('1 cơ sở');
    }

    public function test_gop_san_cu_vao_co_so_khac(): void
    {
        $pl = San::factory()->cua($this->chu)->create(['ten' => 'Sân Phước Long']);
        $pl2 = San::factory()->cua($this->chu)->create(['ten' => 'Sân Phước Long 2']);
        $cu = $pl2->co_so_id;

        $this->actingAs($this->chu)->put("/quan-ly/san/{$pl2->id}", [
            'chuyen_co_so_id' => $pl->co_so_id, 'ten' => 'Sân Phước Long 2', 'loai_san' => 5,
        ])->assertSessionHasNoErrors();

        $this->assertSame($pl->co_so_id, $pl2->fresh()->co_so_id);
        $this->assertSame($pl->dia_chi_chi_tiet, $pl2->fresh()->dia_chi_chi_tiet);
        $this->assertNull(CoSo::find($cu));
    }
}
