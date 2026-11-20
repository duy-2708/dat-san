<?php

namespace Tests\Feature\GiaiDoan10;

use App\Http\Middleware\ChuanHoaNgay;
use App\Models\DatSan;
use App\Models\User;
use App\Models\YeuCauGoPhat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\GiaiDoan3\CoSanDeDat;
use Tests\TestCase;

class ThanhToanKhachTest extends TestCase
{
    use CoSanDeDat, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();
    }

    public function test_khach_thay_khoan_can_coc_va_cham_do(): void
    {
        $khach = User::factory()->create();
        $this->datOnline($khach, $this->a)->assertSessionHasNoErrors();
        $don = DatSan::firstOrFail();

        $this->actingAs($khach)->get('/')->assertSee('Bạn có <strong>1 việc cần làm</strong>', false);
        $this->actingAs($khach)->get('/thanh-toan-cua-toi')->assertOk()
            ->assertSee('Cọc đặt sân')->assertSee($don->ma_don)->assertSee(route('dat-san.thanh-toan', $don));
    }

    public function test_chu_san_go_han_che_phai_qua_admin(): void
    {
        $khach = User::factory()->create(['han_che_luc' => now(), 'ly_do_han_che' => 'Bùng kèo']);
        \Illuminate\Support\Facades\DB::table('keo')->insert([
            'id' => 50, 'loai' => 'doi_dau', 'nguoi_tao_id' => $khach->id, 'san_id' => $this->a->id, 'ngay' => '2026-11-18',
            'trang_thai' => 'da_huy', 'trinh_do' => 'kha', 'tien_san' => 300000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('lich_su_uy_tin')->insert([
            'user_id' => $khach->id, 'keo_id' => 50, 'thay_doi' => -10, 'ly_do' => 'vang_mat', 'created_at' => now(),
        ]);

        $this->actingAs($this->chu)->patch("/quan-ly/han-che/{$khach->id}/go", ['ly_do' => 'Nhầm'])->assertSessionHasNoErrors();
        $this->assertNotNull($khach->fresh()->han_che_luc);
        $yc = YeuCauGoPhat::firstOrFail();

        $this->actingAs(User::factory()->admin()->create())->get('/admin/duyet-go-phat')->assertOk()->assertSee($khach->name);
        $this->actingAs(User::factory()->admin()->create())->patch("/admin/duyet-go-phat/{$yc->id}", ['dong_y' => 1])->assertSessionHasNoErrors();
        $this->assertNull($khach->fresh()->han_che_luc);
        $this->assertSame('da_duyet', $yc->fresh()->trang_thai);
    }

    public function test_nhap_ngay_dd_mm_yyyy(): void
    {
        $this->assertSame('2026-11-20', ChuanHoaNgay::doi('20/11/2026'));
        $this->assertSame('2026-11-20 19:30', ChuanHoaNgay::doi('20/11/2026 19:30'));
        $this->assertNull(ChuanHoaNgay::doi('31/02/2026'));

        $khach = User::factory()->create();
        $this->actingAs($khach)->post('/dat-san', ['san_id' => $this->a->id, 'ngay' => '18/11/2026', 'khung_gio_id' => $this->khung($this->a)->id])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-11-18', substr((string) DatSan::firstOrFail()->ngay, 0, 10));
    }
}
