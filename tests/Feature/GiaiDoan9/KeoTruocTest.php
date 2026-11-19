<?php

namespace Tests\Feature\GiaiDoan9;

use App\Models\DatSan;
use App\Models\Doi;
use App\Models\GiaoDich;
use App\Models\Keo;
use App\Models\User;
use App\Models\XinKeo;
use App\Services\DoiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\GiaiDoan3\CoSanDeDat;
use Tests\TestCase;

class KeoTruocTest extends TestCase
{
    use CoSanDeDat;
    use RefreshDatabase;

    private User $truongA;
    private User $truongB;
    private Doi $doiA;
    private Doi $doiB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();
        $sv = app(DoiService::class);
        $this->truongA = User::factory()->create(['name' => 'Đội trưởng A']);
        $this->truongB = User::factory()->create(['name' => 'Đội trưởng B']);
        $this->doiA = $sv->taoDoi($this->truongA, ['ten' => 'FC Alpha'], null);
        $this->doiB = $sv->taoDoi($this->truongB, ['ten' => 'FC Beta'], null);
    }

    private function dangKeo(array $them = []): Keo
    {
        $this->actingAs($this->truongA)->post('/keo/truoc', $them + [
            'san_id' => $this->a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->a, '17:00')->id,
            'thoi_luong' => 60, 'doi_id' => $this->doiA->id, 'ty_le_thua' => 70, 'trinh_do' => 'trung_binh', 'cong_khai' => 1,
        ])->assertSessionHasNoErrors();

        return Keo::latest('id')->firstOrFail();
    }

    private function chotVoiB(Keo $k): void
    {
        $this->actingAs($this->truongB)->post("/keo/{$k->id}/xin", ['doi_id' => $this->doiB->id])->assertSessionHasNoErrors();
        $x = XinKeo::where('user_id', $this->truongB->id)->firstOrFail();
        $this->actingAs($this->truongA)->patch("/xin-keo/{$x->id}", ['dong_y' => 1])->assertSessionHasNoErrors();
    }

    public function test_cap_keo_truoc_chua_giu_san_chot_xong_moi_dat_va_coc(): void
    {
        $k = $this->dangKeo();
        $this->assertNull($k->dat_san_id);
        $this->assertSame(0, DB::table('o_lich')->count());                      // sân chưa bị giữ
        $this->get('/keo')->assertSee('FC Alpha')->assertSee('chưa đặt sân');

        $this->chotVoiB($k);
        $k->refresh();
        $this->assertSame('cho_coc', $k->trang_thai);
        $don = DatSan::findOrFail($k->dat_san_id);
        $this->assertSame('cho_thanh_toan', $don->trang_thai);
        $this->assertSame($this->doiB->id, (int) $k->doi_b_id);
        $coc = GiaoDich::where('loai', 'coc_keo_doi')->get();
        $this->assertCount(2, $coc);
        $this->assertTrue($coc->every(fn ($g) => (int) $g->so_tien === 45000));   // 15% của 300.000

        // Sân không xác nhận thẳng đơn của kèo được, phải xác nhận từng đội
        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/xac-nhan-coc")->assertSessionHasErrors('dat_san');
        foreach ($coc as $g) {
            $this->actingAs($this->chu)->patch("/quan-ly/coc-keo/{$g->id}/xac-nhan")->assertSessionHasNoErrors();
        }
        $this->assertSame('da_chot', $k->fresh()->trang_thai);
        $this->assertSame('da_dat', $don->fresh()->trang_thai);
        $this->get("/keo/{$k->id}")->assertOk()->assertSee('FC Beta');
    }

    public function test_het_gio_coc_doi_chua_coc_bi_tru_uy_tin_doi_da_coc_cho_hoan(): void
    {
        $k = $this->dangKeo();
        $this->chotVoiB($k);
        $gA = GiaoDich::where('nguoi_tra_id', $this->truongA->id)->firstOrFail();
        $this->actingAs($this->chu)->patch("/quan-ly/coc-keo/{$gA->id}/xac-nhan");

        $this->travel(16)->minutes();                                            // giữ chỗ mặc định 15'
        $this->artisan('keo:quet')->assertSuccessful();

        $k->refresh();
        $this->assertSame('da_huy', $k->trang_thai);
        $this->assertSame('cho_hoan', $gA->fresh()->xu_ly_hoan);
        $this->assertSame(95, $this->truongB->fresh()->diem_uy_tin);
        $this->assertSame(100, $this->truongA->fresh()->diem_uy_tin);
        $this->assertSame(0, DB::table('o_lich')->count());
        $this->datOnline(User::factory()->create(), $this->a, '17:00')->assertSessionHasNoErrors(); // sân được nhả
    }

    public function test_san_mo_o_keo_them_doi_va_duyet(): void
    {
        $this->actingAs($this->chu)->post('/keo/truoc', [
            'san_id' => $this->a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->a, '19:00')->id,
            'ty_le_thua' => 50, 'trinh_do' => 'kha',
        ])->assertSessionHasNoErrors();
        $k = Keo::firstOrFail();
        $this->assertNull($k->dai_dien_a_id);

        $this->actingAs($this->chu)->post("/keo/{$k->id}/them-doi", ['doi' => 'FC Alpha'])->assertSessionHasNoErrors();
        $this->assertSame($this->truongA->id, (int) $k->fresh()->dai_dien_a_id);

        $this->actingAs($this->truongB)->post("/keo/{$k->id}/xin", ['doi_id' => $this->doiB->id]);
        $nv = User::factory()->nhanVienCua($this->chu)->create();
        $x = XinKeo::where('user_id', $this->truongB->id)->firstOrFail();
        $this->actingAs($nv)->patch("/xin-keo/{$x->id}", ['dong_y' => 1])->assertSessionHasNoErrors();
        $this->assertSame('cho_coc', $k->fresh()->trang_thai);
    }

    public function test_keo_rieng_chi_xem_bang_link_va_bat_buoc_chon_doi(): void
    {
        $k = $this->dangKeo(['cong_khai' => 0]);
        $this->get('/keo')->assertDontSee('FC Alpha');
        $this->actingAs(User::factory()->create())->get("/keo/{$k->id}")->assertNotFound();
        $this->actingAs($this->truongB)->get("/keo/{$k->id}?ma={$k->ma_moi}")->assertOk()->assertSee('Xin nhận kèo');

        // Không có đội → không tạo được kèo đội đấu đội
        $this->actingAs(User::factory()->create())->post('/keo/truoc', [
            'san_id' => $this->a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->a, '20:00')->id,
            'ty_le_thua' => 70, 'trinh_do' => 'trung_binh',
        ])->assertSessionHasErrors('keo');
    }

    public function test_san_bi_dat_mat_truoc_khi_chot_thi_khong_chot_duoc(): void
    {
        $k = $this->dangKeo();
        $this->datOnline(User::factory()->create(), $this->a, '17:00')->assertSessionHasNoErrors();
        $this->actingAs($this->truongB)->post("/keo/{$k->id}/xin", ['doi_id' => $this->doiB->id]);
        $x = XinKeo::firstOrFail();
        $this->actingAs($this->truongA)->patch("/xin-keo/{$x->id}", ['dong_y' => 1])->assertSessionHasErrors('dat_san');
        $this->assertSame('dang_tim', $k->fresh()->trang_thai);
    }
}
