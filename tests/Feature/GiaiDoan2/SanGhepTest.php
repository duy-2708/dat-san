<?php

namespace Tests\Feature\GiaiDoan2;

use App\Models\San;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanGhepTest extends TestCase
{
    use RefreshDatabase;

    private User $chu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chu = User::factory()->chuSan()->create();
    }

    private function capNhat(San $san, array $conIds)
    {
        return $this->actingAs($this->chu)->put("/quan-ly/san/{$san->id}", [
            'ten' => $san->ten, 'loai_san' => $san->loai_san, 'xa_phuong_id' => $san->xa_phuong_id,
            'dia_chi_chi_tiet' => $san->dia_chi_chi_tiet, 'gio_mo_cua' => '06:00', 'gio_dong_cua' => '23:00',
            'san_con_ids' => $conIds,
        ]);
    }

    public function test_ghep_san_7_tu_hai_san_5(): void
    {
        $a = San::factory()->cua($this->chu)->create();
        $b = San::factory()->cua($this->chu)->create();
        $lon = San::factory()->cua($this->chu)->loai(7)->create();

        $this->capNhat($lon, [$a->id, $b->id])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $lon->sanCons()->pluck('san.id')->all());
    }

    public function test_khong_ghep_san_cua_nguoi_khac(): void
    {
        $lon = San::factory()->cua($this->chu)->loai(7)->create();
        $sanLa = San::factory()->create(); // của chủ khác

        $this->capNhat($lon, [$sanLa->id])->assertSessionHasErrors('san_con_ids');
        $this->assertSame(0, $lon->sanCons()->count());
    }

    public function test_khong_ghep_san_bang_hoac_lon_hon(): void
    {
        $bay = San::factory()->cua($this->chu)->loai(7)->create();
        $bayKhac = San::factory()->cua($this->chu)->loai(7)->create();
        $muoiMot = San::factory()->cua($this->chu)->loai(11)->create();

        $this->capNhat($bay, [$bayKhac->id])->assertSessionHasErrors('san_con_ids');
        $this->capNhat($bay, [$muoiMot->id])->assertSessionHasErrors('san_con_ids');
        $this->capNhat($bay, [$bay->id])->assertSessionHasErrors('san_con_ids');
    }

    public function test_san_lien_quan_tinh_bac_cau(): void
    {
        // 11 ← 7 ← (5A, 5B);  11 ← 5C
        $a = San::factory()->cua($this->chu)->create();
        $b = San::factory()->cua($this->chu)->create();
        $c = San::factory()->cua($this->chu)->create();
        $bay = San::factory()->cua($this->chu)->loai(7)->create();
        $mm = San::factory()->cua($this->chu)->loai(11)->create();
        $bay->sanCons()->sync([$a->id, $b->id]);
        $mm->sanCons()->sync([$bay->id, $c->id]);

        $this->assertEqualsCanonicalizing([$bay->id, $a->id, $b->id, $c->id], $mm->idsSanLienQuan());
        $this->assertEqualsCanonicalizing([$bay->id, $mm->id], $a->idsSanLienQuan()); // 5A không dính 5B, 5C
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $mm->id], $bay->idsSanLienQuan());
    }

    public function test_khong_doi_san_con_thanh_loai_lon_hon_san_cha(): void
    {
        $a = San::factory()->cua($this->chu)->create();
        $bay = San::factory()->cua($this->chu)->loai(7)->create();
        $bay->sanCons()->sync([$a->id]);

        $this->actingAs($this->chu)->put("/quan-ly/san/{$a->id}", [
            'ten' => $a->ten, 'loai_san' => 7, 'xa_phuong_id' => $a->xa_phuong_id,
            'dia_chi_chi_tiet' => $a->dia_chi_chi_tiet, 'gio_mo_cua' => '06:00', 'gio_dong_cua' => '23:00',
        ])->assertSessionHasErrors('loai_san');

        $this->assertSame(5, $a->fresh()->loai_san);
    }

    public function test_xoa_san_go_lien_ket_ghep(): void
    {
        $a = San::factory()->cua($this->chu)->create();
        $bay = San::factory()->cua($this->chu)->loai(7)->create();
        $bay->sanCons()->sync([$a->id]);

        $this->actingAs($this->chu)->delete("/quan-ly/san/{$a->id}")->assertRedirect(route('quan-ly.san.index'));

        $this->assertNull(San::find($a->id));
        $this->assertSame(0, $bay->sanCons()->count());
    }
}
