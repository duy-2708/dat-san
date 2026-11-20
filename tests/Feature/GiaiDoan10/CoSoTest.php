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

    public function test_chi_ghep_trong_cung_co_so(): void
    {
        $cs = $this->taoCoSo(2);
        $khac = San::factory()->cua($this->chu)->create(['ten' => 'Sân Phước Long']);
        $this->actingAs($this->chu)->post('/quan-ly/san', ['co_so_id' => $cs->id, 'ten' => 'Sân 7A', 'loai_san' => 7, 'san_con_ids' => [$khac->id]])
            ->assertSessionHasErrors('san_con_ids');

        $ids = $cs->sans()->pluck('id')->all();
        $this->actingAs($this->chu)->post('/quan-ly/san', ['co_so_id' => $cs->id, 'ten' => 'Sân 7A', 'loai_san' => 7, 'san_con_ids' => $ids])
            ->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing($ids, San::where('ten', 'Sân 7A')->firstOrFail()->sanCons()->pluck('san.id')->all());
        $this->actingAs($this->chu)->get('/quan-ly/san/tao?co_so='.$cs->id)->assertOk()->assertSee('id="ghep'.$ids[0].'"', false)->assertDontSee('id="ghep'.$khac->id.'"', false);
    }

    public function test_xoa_san_tao_nham_va_xoa_ca_co_so(): void
    {
        $cs = $this->taoCoSo(3);
        [$a, $b, $c] = $cs->sans()->get()->all();
        app(DatSanService::class)->ghiDon($a, '2026-11-18', 17 * 60, 18 * 60, [
            'ten_khach_tai_cho' => 'Khách', 'sdt_khach_tai_cho' => '0988777666',
            'gia_chot' => 300000, 'tien_coc' => 0, 'nguon' => 'tai_cho', 'trang_thai' => 'da_dat',
        ]);

        $this->actingAs($this->chu)->delete("/quan-ly/san/{$c->id}")->assertSessionHasNoErrors();
        $this->assertNull(San::find($c->id));
        $this->actingAs($this->chu)->delete("/quan-ly/san/{$a->id}")->assertSessionHasErrors('san');
        $this->actingAs($this->chu)->delete("/quan-ly/co-so/{$cs->id}")->assertSessionHasErrors('san');
        $this->assertNotNull(CoSo::find($cs->id));

        $cs2 = CoSo::find($this->taoCoSo2()->id);
        $this->actingAs($this->chu)->delete("/quan-ly/co-so/{$cs2->id}")->assertSessionHasNoErrors();
        $this->assertNull(CoSo::find($cs2->id));
        $this->assertSame(0, San::where('co_so_id', $cs2->id)->count());
        $this->actingAs(User::factory()->chuSan()->create())->delete("/quan-ly/co-so/{$cs->id}")->assertForbidden();
    }

    private function taoCoSo2(): CoSo
    {
        $this->actingAs($this->chu)->post('/quan-ly/san', [
            'ten_co_so' => 'Cơ Sở Nhầm', 'ten' => 'Sân X', 'loai_san' => 5, 'so_luong' => 2,
            'xa_phuong_id' => XaPhuong::factory()->create()->id, 'dia_chi_chi_tiet' => '1 Trần Phú',
            'gio_mo_cua' => '06:00', 'gio_dong_cua' => '23:00',
        ])->assertSessionHasNoErrors();

        return CoSo::where('ten', 'Cơ Sở Nhầm')->firstOrFail();
    }

    public function test_tao_nhanh_gia_cuoi_tuan_va_le_khac(): void
    {
        $cs = $this->taoCoSo(1);
        $a = $cs->sans()->first();
        $this->actingAs($this->chu)->post("/quan-ly/san/{$a->id}/khung-gio/tao-nhanh", [
            'loai_ngays' => ['ngay_thuong', 'cuoi_tuan', 'ngay_le'], 'thoi_luong' => 60, 'gia_khac_ngay' => 1,
            'muc' => [['tu' => '06:00', 'den' => '23:00', 'gia' => '200.000', 'gia_cuoi_tuan' => '250.000', 'gia_le' => '']],
        ])->assertSessionHasNoErrors();

        $gia = fn ($loai) => (int) $a->khungGios()->where('loai_ngay', $loai)->where('gio_bat_dau', '17:00:00')->value('gia');
        $this->assertSame(200000, $gia('ngay_thuong'));
        $this->assertSame(250000, $gia('cuoi_tuan'));
        $this->assertSame(200000, $gia('ngay_le'));

        $this->actingAs($this->chu)->post("/quan-ly/san/{$a->id}/khung-gio/tao-nhanh", [
            'loai_ngays' => ['ngay_thuong'], 'thoi_luong' => 60, 'muc' => [['tu' => '06:00', 'den' => '23:00', 'gia' => '200000000000000']],
        ])->assertSessionHasErrors('gia');
    }

    public function test_chay_thu_xoa_han_san_da_co_luot_dat(): void
    {
        $cs = $this->taoCoSo(1);
        $a = $cs->sans()->first();
        $this->actingAs($this->chu)->post("/quan-ly/san/{$a->id}/khung-gio/tao-nhanh", ['loai_ngays' => ['ngay_thuong'], 'thoi_luong' => 60, 'gia' => 300000]);
        $this->actingAs(User::factory()->create())->post('/dat-san', [
            'san_id' => $a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $a->khungGios()->where('gio_bat_dau', '17:00:00')->value('id'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, DatSan::count());

        $this->actingAs(User::factory()->chuSan()->create())->delete("/quan-ly/san/{$a->id}/xoa-han")->assertForbidden();
        $this->actingAs($this->chu)->delete("/quan-ly/san/{$a->id}/xoa-han")->assertSessionHasNoErrors();
        $this->assertNull(San::find($a->id));
        $this->assertSame(0, DatSan::count());
        $this->assertNull(CoSo::find($cs->id));
    }

    public function test_dat_gio_le_va_mot_gia_phu_ca_ngay(): void
    {
        $cs = $this->taoCoSo(2);
        [$a, $b] = $cs->sans()->get()->all();
        $this->actingAs($this->chu)->post("/quan-ly/san/{$a->id}/khung-gio/tao-nhanh", [
            'loai_ngays' => ['ngay_thuong'], 'thoi_luong' => 60,
            'muc' => [['tu' => '06:00', 'den' => '11:00', 'gia' => '200.000'], ['tu' => '11:00', 'den' => '23:00', 'gia' => '']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(17, $a->khungGios()->count());

        $k = $a->khungGios()->where('gio_bat_dau', '17:00:00')->firstOrFail();
        $khach = User::factory()->create();
        $this->actingAs($khach)->get("/san/{$a->slug}")->assertSee('Còn 2/2 sân: A, B')->assertSee('Bắt đầu 17:30');

        $this->actingAs($khach)->post('/dat-san', ['san_id' => $a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $k->id, 'bat_dau' => '17:30', 'thoi_luong' => 90])
            ->assertSessionHasNoErrors();
        $don = DatSan::firstOrFail();
        $this->assertSame('17:30', substr($don->gio_bat_dau, 0, 5));
        $this->assertSame('19:00', substr($don->gio_ket_thuc, 0, 5));
        $this->assertSame(300000, (int) $don->gia_chot);

        $this->actingAs(User::factory()->create())->post('/dat-san', ['san_id' => $a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $k->id, 'bat_dau' => '18:15'])
            ->assertSessionHasErrors();
    }
}
