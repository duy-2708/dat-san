<?php

namespace Tests\Feature\GiaiDoan14;

use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\Keo;
use App\Models\ThuTienKeo;
use App\Models\User;
use App\Services\DatSanService;
use App\Services\KeoService;
use App\Services\ThongKeThu;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\GiaiDoan3\CoSanDeDat;
use Tests\TestCase;

class ThuTienTest extends TestCase
{
    use CoSanDeDat, RefreshDatabase;

    private User $khach;
    private DatSan $don;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();
        $this->khach = $this->khachCu('Đội A');
        $this->don = app(DatSanService::class)->ghiDon($this->a, '2026-11-18', 17 * 60, 18 * 60, [
            'khach_id' => $this->khach->id, 'khung_gio_id' => $this->khung($this->a)->id, 'gia_chot' => 300000,
            'tien_coc' => 90000, 'nguon' => 'online', 'trang_thai' => 'da_dat', 'xac_nhan_coc_luc' => now(),
        ]);
    }

    private function khachCu(string $ten): User
    {
        $u = User::factory()->create(['name' => $ten]);
        $u->forceFill(['created_at' => now()->subMonths(3), 'so_tran_tham_gia' => 5])->save();
        app(\App\Services\DoiService::class)->taoDoi($u, ['ten' => 'Đội của '.$ten.' '.$u->id], null);

        return $u;
    }

    /** Kèo đội đấu đội đã có kết quả hai bên khớp nhau. */
    private function keoDaChot(): Keo
    {
        $this->actingAs($this->khach)->post('/keo', [
            'ma_don' => $this->don->ma_don, 'loai' => 'doi_dau', 'ty_le_thua' => 70, 'trinh_do' => 'trung_binh',
        ])->assertSessionHasNoErrors();
        $k = Keo::firstOrFail();
        $b = $this->khachCu('Đội B');
        $this->actingAs($b)->post("/keo/{$k->id}/nhan")->assertRedirect();
        $gd = GiaoDich::where('loai', 'coc_keo_doi')->firstOrFail();
        $this->actingAs($this->chu)->patch("/quan-ly/coc-keo/{$gd->id}/xac-nhan")->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2026-11-18 18:05'));
        $this->actingAs($this->khach)->post("/keo/{$k->id}/ket-qua", ['ket_qua' => 'a_thang']);
        $this->actingAs($b)->post("/keo/{$k->id}/ket-qua", ['ket_qua' => 'a_thang']);

        return $k->fresh();
    }

    public function test_thu_tien_san_phai_chon_hinh_thuc_va_vao_dung_muc_thong_ke(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 18:05'));

        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$this->don->ma_don}/da-thu", ['so_tien' => '210.000'])
            ->assertSessionHasErrors('phuong_thuc');

        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$this->don->ma_don}/da-thu", ['so_tien' => '210.000', 'phuong_thuc' => 'online'])
            ->assertSessionHasNoErrors();

        $tk = app(ThongKeThu::class)->thang($this->chu->id, Carbon::parse('2026-11-18'));
        $this->assertSame(210000, $tk['online']);
        $this->assertSame(0, $tk['tien_mat']);
        $this->assertSame(0, $tk['tien_san_chua_ro']);
    }

    public function test_nhan_vien_ghi_thu_duoc_con_khach_thi_khong(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 18:05'));
        $nv = User::factory()->nhanVienCua($this->chu)->create();

        $this->actingAs($nv)->patch("/quan-ly/dat-san/{$this->don->ma_don}/da-thu", ['so_tien' => '210.000', 'phuong_thuc' => 'tien_mat'])
            ->assertSessionHasNoErrors();
        $this->assertSame('tien_mat', $this->don->fresh()->phuong_thuc_thu_tai_san);

        $this->actingAs($this->khach)->patch("/quan-ly/dat-san/{$this->don->ma_don}/da-thu", ['so_tien' => '1.000', 'phuong_thuc' => 'tien_mat'])
            ->assertForbidden();
    }

    public function test_khach_mo_qr_tra_con_dung_so_tien_con_lai(): void
    {
        $this->actingAs($this->khach)->get("/dat-san/{$this->don->ma_don}/tra-con")->assertOk()
            ->assertSee('210.000đ')->assertSee($this->don->ma_don);

        $this->actingAs(User::factory()->create())->get("/dat-san/{$this->don->ma_don}/tra-con")->assertForbidden();
    }

    public function test_nut_thu_tien_san_hien_tien_mat_va_online_o_don_dat(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 18:05'));

        $this->actingAs($this->chu)->get('/quan-ly/dat-san')->assertOk()
            ->assertSee('Chờ thu tiền sân')->assertSee('value="tien_mat"', false)->assertSee('value="online"', false);
    }

    public function test_chu_san_ghi_thu_ben_tra_them_va_thong_ke_theo_kieu(): void
    {
        $k = $this->keoDaChot();
        $q = app(KeoService::class)->quyetToan($k);
        $ben = $q['a']['con'] > 0 ? 'a' : 'b';
        $con = $q[$ben]['con'];
        $this->assertGreaterThan(0, $con);

        $this->actingAs($this->chu)->patch("/quan-ly/keo/{$k->id}/thu-tien", ['ben' => $ben, 'loai' => 'thu', 'so_tien' => (string) $con, 'phuong_thuc' => 'online'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, ThuTienKeo::count());

        $this->actingAs($this->chu)->patch("/quan-ly/keo/{$k->id}/thu-tien", ['ben' => $ben, 'loai' => 'thu', 'so_tien' => '1000', 'phuong_thuc' => 'tien_mat'])
            ->assertSessionHasErrors('keo');

        $this->actingAs($this->chu)->get("/keo/{$k->id}")->assertOk()->assertSee('Đã thu')->assertSee('online');

        $tk = app(ThongKeThu::class)->thang($this->chu->id, Carbon::parse('2026-11-18'));
        $this->assertSame($con, $tk['keo']);
    }

    public function test_khong_ghi_tra_lai_khi_khong_co_tien_tra_lai(): void
    {
        $k = $this->keoDaChot();

        $this->actingAs($this->chu)->patch("/quan-ly/keo/{$k->id}/thu-tien", ['ben' => 'a', 'loai' => 'tra_lai', 'so_tien' => '1000', 'phuong_thuc' => 'tien_mat'])
            ->assertSessionHasErrors('keo');
    }

    public function test_khach_khong_ghi_duoc_thu_tien_keo(): void
    {
        $k = $this->keoDaChot();

        $this->actingAs($this->khach)->patch("/quan-ly/keo/{$k->id}/thu-tien", ['ben' => 'b', 'loai' => 'thu', 'so_tien' => '1000', 'phuong_thuc' => 'tien_mat'])
            ->assertForbidden();
    }
}
