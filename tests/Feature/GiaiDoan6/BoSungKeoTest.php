<?php

namespace Tests\Feature\GiaiDoan6;

use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\Keo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\GiaiDoan3\CoSanDeDat;
use Tests\TestCase;

/** Các phần bổ sung: sân tạo kèo hộ, sân huỷ kèo + hoàn cọc, huỷ lịch kéo theo kèo, admin gỡ hạn chế. */
class BoSungKeoTest extends TestCase
{
    use RefreshDatabase, CoSanDeDat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();
    }

    private function khachCu(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['created_at' => now()->subMonths(3), 'so_tran_tham_gia' => 5])->save();
        app(\App\Services\DoiService::class)->taoDoi($u, ['ten' => 'Đội khách '.$u->id], null); // kèo đội đấu đội bắt buộc có đội

        return $u;
    }

    private function datTaiCho(): DatSan
    {
        $this->actingAs($this->chu)->post("/quan-ly/san/{$this->a->id}/dat-tai-cho", [
            'ngay' => '2026-11-18', 'khung_gio_id' => $this->khung($this->a)->id,
            'ten' => 'Đội Anh Tuấn', 'sdt' => '0912345678', 'da_thu_coc' => 1,
        ])->assertSessionHasNoErrors();

        return DatSan::firstOrFail();
    }

    /** Kèo đội đấu đội đã có bên B cọc xong. */
    private function keoCoBDaCoc(DatSan $don): array
    {
        $this->actingAs($this->chu)->post("/quan-ly/dat-san/{$don->ma_don}/keo", [
            'loai' => 'doi_dau', 'ty_le_thua' => 70, 'trinh_do' => 'kha',
        ])->assertSessionHasNoErrors();
        $k = Keo::firstOrFail();
        $b = $this->khachCu();
        $this->actingAs($b)->post("/keo/{$k->id}/nhan");
        $gd = GiaoDich::where('loai', 'coc_keo_doi')->firstOrFail();
        $this->actingAs($this->chu)->patch("/quan-ly/coc-keo/{$gd->id}/xac-nhan");

        return [$k->fresh(), $b, $gd];
    }

    public function test_chu_san_tao_keo_ho_lich_tai_cho_va_bao_ket_qua_thay_ben_a(): void
    {
        $don = $this->datTaiCho();
        [$k, $b] = $this->keoCoBDaCoc($don);

        $this->assertSame('da_chot', $k->trang_thai);
        $this->assertSame(0, $k->thanhVien('a')->count());              // bên A là khách tại sân, không có tài khoản

        $this->travelTo(Carbon::parse('2026-11-18 18:05'));
        $this->actingAs($this->chu)->post("/keo/{$k->id}/ket-qua", ['ket_qua' => 'b_thang'])->assertSessionHasNoErrors();
        $this->actingAs($b)->post("/keo/{$k->id}/ket-qua", ['ket_qua' => 'b_thang']);
        $this->assertSame('hoan_tat', $k->fresh()->trang_thai);
    }

    public function test_san_khong_tao_keo_ho_lich_online_cua_khach(): void
    {
        $this->datOnline(User::factory()->create(), $this->a);
        $don = DatSan::firstOrFail();
        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/xac-nhan-coc");

        $this->actingAs($this->chu)->post("/quan-ly/dat-san/{$don->ma_don}/keo", [
            'loai' => 'doi_dau', 'ty_le_thua' => 70, 'trinh_do' => 'kha',
        ])->assertSessionHasErrors('keo');

        $this->actingAs(User::factory()->chuSan()->create())->get("/quan-ly/dat-san/{$don->ma_don}/tao-keo")->assertForbidden();
    }

    public function test_chu_san_huy_keo_thi_coc_chuyen_cho_hoan(): void
    {
        [$k, $b, $gd] = $this->keoCoBDaCoc($this->datTaiCho());

        $this->actingAs($this->chu)->patch("/quan-ly/keo/{$k->id}/huy", ['ly_do' => 'Hỏng đèn'])->assertSessionHasNoErrors();

        $this->assertSame('da_huy', $k->fresh()->trang_thai);
        $this->assertSame('cho_hoan', $gd->fresh()->xu_ly_hoan);
        $this->actingAs($this->chu)->get('/quan-ly/dat-san')->assertSee('Cọc kèo cần hoàn');

        $this->actingAs($this->chu)->patch("/quan-ly/coc-keo/{$gd->id}/da-hoan")->assertSessionHasNoErrors();
        $this->assertSame('da_hoan', $gd->fresh()->xu_ly_hoan);

        // Nhân viên không huỷ kèo được
        $nv = User::factory()->nhanVienCua($this->chu)->create();
        $this->actingAs($nv)->patch("/quan-ly/keo/{$k->id}/huy")->assertForbidden();
    }

    public function test_huy_lich_san_thi_kem_huy_keo_va_hoan_coc_ben_b(): void
    {
        $don = $this->datTaiCho();
        [$k, , $gd] = $this->keoCoBDaCoc($don);

        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/huy", ['ly_do' => 'Mưa ngập']);

        $this->assertSame('da_huy', $k->fresh()->trang_thai);
        $this->assertSame('cho_hoan', $gd->fresh()->xu_ly_hoan);
    }

    public function test_admin_go_han_che(): void
    {
        $u = $this->khachCu();
        $u->forceFill(['han_che_luc' => now(), 'ly_do_han_che' => 'Không xác nhận kết quả kèo #1'])->save();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/tai-khoan')->assertSee('Gỡ hạn chế');
        $this->actingAs($admin)->patch("/admin/tai-khoan/{$u->id}/go-han-che")->assertSessionHasNoErrors();
        $this->assertNull($u->fresh()->han_che_luc);

        $this->actingAs($this->chu)->patch("/admin/tai-khoan/{$u->id}/go-han-che")->assertForbidden();
    }
}
