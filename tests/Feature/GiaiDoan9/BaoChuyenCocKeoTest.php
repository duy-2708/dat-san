<?php

namespace Tests\Feature\GiaiDoan9;

use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\Keo;
use App\Models\User;
use App\Models\XinKeo;
use App\Notifications\KhachBaoDaChuyenCoc;
use App\Services\DoiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\GiaiDoan3\CoSanDeDat;
use Tests\TestCase;

class BaoChuyenCocKeoTest extends TestCase
{
    use CoSanDeDat;
    use RefreshDatabase;

    private User $truongA;
    private User $truongB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();
        $sv = app(DoiService::class);
        $this->truongA = User::factory()->create();
        $this->truongB = User::factory()->create();
        $doiA = $sv->taoDoi($this->truongA, ['ten' => 'FC Alpha'], null);
        $doiB = $sv->taoDoi($this->truongB, ['ten' => 'FC Beta'], null);

        $this->actingAs($this->truongA)->post('/keo/truoc', [
            'san_id' => $this->a->id, 'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->a, '17:00')->id,
            'doi_id' => $doiA->id, 'ty_le_thua' => 70, 'trinh_do' => 'trung_binh', 'cong_khai' => 1,
        ]);
        $k = Keo::firstOrFail();
        $this->actingAs($this->truongB)->post("/keo/{$k->id}/xin", ['doi_id' => $doiB->id]);
        $this->actingAs($this->truongA)->patch('/xin-keo/'.XinKeo::firstOrFail()->id, ['dong_y' => 1]);
    }

    private function cocCua(User $u): GiaoDich
    {
        return GiaoDich::where('nguoi_tra_id', $u->id)->where('loai', 'coc_keo_doi')->firstOrFail();
    }

    public function test_bao_da_chuyen_coc_keo_mot_lan_keo_dai_han_va_bao_email(): void
    {
        Notification::fake();
        $g = $this->cocCua($this->truongB);

        $this->actingAs($this->truongB)->get("/keo/coc/{$g->id}")->assertOk()->assertSee('Tôi đã chuyển khoản');
        $this->actingAs($this->truongB)->post("/keo/coc/{$g->id}/da-chuyen")->assertSessionHasNoErrors();
        $this->actingAs($this->truongB)->post("/keo/coc/{$g->id}/da-chuyen")->assertSessionHasErrors('keo');
        $this->actingAs($this->truongA)->post("/keo/coc/{$g->id}/da-chuyen")->assertSessionHasErrors('keo');   // không phải người cọc

        Notification::assertSentTo($this->chu, KhachBaoDaChuyenCoc::class);
        $k = Keo::firstOrFail();
        $this->assertTrue($k->han_coc_luc->gte(now()->addMinutes(119)));
        $this->assertTrue(DatSan::findOrFail($k->dat_san_id)->giu_cho_den->gte(now()->addMinutes(119)));

        // Hết 15' giữ chỗ ban đầu kèo vẫn còn
        $this->travel(20)->minutes();
        $this->artisan('keo:quet');
        $this->assertSame('cho_coc', $k->fresh()->trang_thai);
        $this->actingAs($this->chu)->get('/quan-ly/dat-san')->assertSee('Báo đã chuyển lúc');
    }

    public function test_khong_thay_tien_coc_keo_huy_keo_phat_nguoi_bao_sai_hoan_ben_kia(): void
    {
        $gA = $this->cocCua($this->truongA);
        $gB = $this->cocCua($this->truongB);
        $this->actingAs($this->chu)->patch("/quan-ly/coc-keo/{$gA->id}/xac-nhan");
        $this->actingAs($this->truongB)->post("/keo/coc/{$gB->id}/da-chuyen");

        $this->actingAs($this->chu)->patch("/quan-ly/coc-keo/{$gB->id}/khong-thay-tien")->assertSessionHasNoErrors();

        $k = Keo::firstOrFail();
        $this->assertSame('da_huy', $k->trang_thai);
        $this->assertSame(95, $this->truongB->fresh()->diem_uy_tin);           // chỉ phạt báo sai, không phạt thêm bùng kèo
        $this->assertSame('cho_hoan', $gA->fresh()->xu_ly_hoan);
        $this->assertSame('da_huy', DatSan::findOrFail($k->dat_san_id)->trang_thai);
    }
}
