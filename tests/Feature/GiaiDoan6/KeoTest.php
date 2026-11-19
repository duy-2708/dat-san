<?php

namespace Tests\Feature\GiaiDoan6;

use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\Keo;
use App\Models\ThamGiaKeo;
use App\Models\User;
use App\Services\DatSanService;
use App\Services\KeoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\GiaiDoan3\CoSanDeDat;
use Tests\TestCase;

class KeoTest extends TestCase
{
    use RefreshDatabase, CoSanDeDat;

    private User $doiA;   // đội trưởng bên A ($this->a là Sân 5A trong CoSanDeDat)
    private DatSan $don;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();                       // 18/11/2026 08:00, sân 5A giá 300k/giờ
        $this->doiA = $this->khachCu('Đội Trưởng A');
        // A đã đặt 5A 17:00–18:00 và đã cọc 30% (90k)
        $this->don = app(DatSanService::class)->ghiDon($this->a, '2026-11-18', 17 * 60, 18 * 60, [
            'khach_id' => $this->doiA->id, 'khung_gio_id' => $this->khung($this->a)->id, 'gia_chot' => 300000,
            'tien_coc' => 90000, 'nguon' => 'online', 'trang_thai' => 'da_dat', 'xac_nhan_coc_luc' => now(),
        ]);
    }

    /** Khách lâu năm, đã đá vài trận (không bị luật tài khoản mới chặn). */
    private function khachCu(string $ten = 'Khách'): User
    {
        $u = User::factory()->create(['name' => $ten]);
        $u->forceFill(['created_at' => now()->subMonths(3), 'so_tran_tham_gia' => 5])->save();

        return $u;
    }

    private function taoDoiDau(int $tyLe = 70): Keo
    {
        $this->actingAs($this->doiA)->post('/keo', [
            'ma_don' => $this->don->ma_don, 'loai' => 'doi_dau', 'ty_le_thua' => $tyLe, 'trinh_do' => 'trung_binh',
        ])->assertSessionHasNoErrors();

        return Keo::firstOrFail();
    }

    /** B nhận kèo + chủ sân xác nhận cọc → kèo đã chốt. */
    private function chotVoiB(Keo $k): User
    {
        $b = $this->khachCu('Đội Trưởng B');
        $this->actingAs($b)->post("/keo/{$k->id}/nhan")->assertRedirect();
        $gd = GiaoDich::where('loai', 'coc_keo_doi')->firstOrFail();
        $this->actingAs($this->chu)->patch("/quan-ly/coc-keo/{$gd->id}/xac-nhan")->assertSessionHasNoErrors();

        return $b;
    }

    public function test_tao_keo_doi_dau_nhan_keo_va_coc_15_phan_tram(): void
    {
        $k = $this->taoDoiDau();
        $this->assertSame('dang_tim', $k->trang_thai);
        $this->assertSame(45000, $k->coc_moi_ben);                  // 15% × 300k
        $this->get("/keo/{$k->id}")->assertOk()->assertSee('Thua chịu');

        $b = $this->khachCu();
        $this->actingAs($b)->post("/keo/{$k->id}/nhan");
        $gd = GiaoDich::where('loai', 'coc_keo_doi')->firstOrFail();
        $this->assertSame(45000, $gd->so_tien);
        $this->assertSame((int) $this->chu->id, (int) $gd->chu_san_id);   // cọc vào tài khoản chủ sân
        $this->assertSame('DK'.str_pad((string) $gd->id, 6, '0', STR_PAD_LEFT), $gd->noi_dung_ck);
        $this->actingAs($b)->get("/keo/coc/{$gd->id}")->assertOk()->assertSee('<svg', false);

        // Đội thứ 3 không nhận được nữa
        $this->actingAs($this->khachCu())->post("/keo/{$k->id}/nhan")->assertSessionHasErrors('keo');

        $this->actingAs($this->chu)->patch("/quan-ly/coc-keo/{$gd->id}/xac-nhan");
        $this->assertSame('da_chot', $k->fresh()->trang_thai);
    }

    public function test_chi_chu_lich_da_coc_moi_tao_duoc_keo(): void
    {
        $this->actingAs($this->khachCu())->post('/keo', [
            'ma_don' => $this->don->ma_don, 'loai' => 'doi_dau', 'ty_le_thua' => 70, 'trinh_do' => 'kha',
        ])->assertSessionHasErrors('keo');
        $this->assertSame(0, Keo::count());
    }

    public function test_ca_hai_ben_bao_khop_thi_hoan_tat_va_quyet_toan(): void
    {
        $k = $this->taoDoiDau(70);
        $b = $this->chotVoiB($k);

        $this->travelTo(Carbon::parse('2026-11-18 18:05'));
        $this->actingAs($this->doiA)->post("/keo/{$k->id}/ket-qua", ['ket_qua' => 'b_thang']); // A bấm nhầm
        $this->actingAs($this->doiA)->post("/keo/{$k->id}/ket-qua", ['ket_qua' => 'a_thang']); // sửa lại
        $this->assertSame('cho_xac_nhan', $k->fresh()->trang_thai);
        $this->actingAs($b)->post("/keo/{$k->id}/ket-qua", ['ket_qua' => 'a_thang']);

        $k->refresh();
        $this->assertSame('hoan_tat', $k->trang_thai);
        $this->assertSame('a_thang', $k->ket_qua);

        // 7/3, A thắng: B chịu 210k (đã cọc 45k → trả thêm 165k), A chịu 90k (đã cọc 90k → 0)
        $q = app(KeoService::class)->quyetToan($k);
        $this->assertSame(['phan' => 90000, 'da_tra' => 90000, 'con' => 0], $q['a']);
        $this->assertSame(['phan' => 210000, 'da_tra' => 45000, 'con' => 165000], $q['b']);

        $this->assertSame(6, $b->fresh()->so_tran_tham_gia);           // có mặt +1 trận
    }

    public function test_bao_lech_thi_tranh_chap_chu_san_chot(): void
    {
        $k = $this->taoDoiDau();
        $b = $this->chotVoiB($k);
        $this->travelTo(Carbon::parse('2026-11-18 18:05'));

        $this->actingAs($this->doiA)->post("/keo/{$k->id}/ket-qua", ['ket_qua' => 'a_thang']);
        $this->actingAs($b)->post("/keo/{$k->id}/ket-qua", ['ket_qua' => 'b_thang']);
        $this->assertSame('tranh_chap', $k->fresh()->trang_thai);

        $nv = User::factory()->nhanVienCua($this->chu)->create();         // nhân viên giám sát chốt
        $this->actingAs($nv)->patch("/quan-ly/keo/{$k->id}/chot", ['ket_qua' => 'hoa'])->assertSessionHasNoErrors();
        $this->assertSame('hoan_tat', $k->fresh()->trang_thai);
        $this->assertSame(150000, app(KeoService::class)->quyetToan($k->fresh())['b']['phan']); // hoà = 5/5

        $this->actingAs(User::factory()->chuSan()->create())->patch("/quan-ly/keo/{$k->id}/chot", ['ket_qua' => 'a_thang'])->assertForbidden();
    }

    public function test_qua_60_phut_chi_han_che_ben_chua_xac_nhan(): void
    {
        $k = $this->taoDoiDau();
        $b = $this->chotVoiB($k);
        $tvB = $this->khachCu('Thành viên B');
        $this->actingAs($b)->post("/keo/{$k->id}/thanh-vien", ['tim' => $tvB->so_dien_thoai])->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2026-11-18 18:10'));
        $this->actingAs($this->doiA)->post("/keo/{$k->id}/ket-qua", ['ket_qua' => 'a_thang']);

        $this->travelTo(Carbon::parse('2026-11-18 19:01'));              // hạn = 18:00 + 60'
        $this->artisan('keo:quet')->assertSuccessful();

        $this->assertNull($this->doiA->fresh()->han_che_luc);               // bên A đã báo → không bị gì
        $this->assertNotNull($b->fresh()->han_che_luc);
        $this->assertNotNull($tvB->fresh()->han_che_luc);                // cả đội B
        $this->assertSame('tranh_chap', $k->fresh()->trang_thai);

        // Bị hạn chế: vẫn đăng nhập, nhưng không đặt sân được
        $this->actingAs($b->fresh())->get('/tai-khoan')->assertOk()->assertSee('Bị hạn chế');
        $this->datOnline($b->fresh(), $this->b, '20:00')->assertSessionHasErrors('dat_san');

        // Chủ sân gỡ hạn chế
        $this->actingAs($this->chu)->patch("/quan-ly/han-che/{$b->id}/go")->assertSessionHasNoErrors();
        $this->assertNull($b->fresh()->han_che_luc);
        $this->actingAs(User::factory()->chuSan()->create())->patch("/quan-ly/han-che/{$tvB->id}/go")->assertForbidden();
    }

    public function test_ca_doi_vang_bi_tru_uy_tin_chu_san_mien_phat_nguoi_co_den(): void
    {
        $k = $this->taoDoiDau();
        $b = $this->chotVoiB($k);
        $tvB = $this->khachCu('Người Có Đến');
        $this->actingAs($b)->post("/keo/{$k->id}/thanh-vien", ['tim' => $tvB->email]);

        $this->travelTo(Carbon::parse('2026-11-18 17:20'));
        $this->actingAs($this->chu)->post("/keo/{$k->id}/diem-danh", ['ben' => 'b', 'co_mat' => 0])->assertSessionHasNoErrors();

        $this->assertSame(90, $b->fresh()->diem_uy_tin);
        $this->assertSame(1, $tvB->fresh()->so_tran_vang);

        $tg = ThamGiaKeo::where('user_id', $tvB->id)->firstOrFail();
        $this->actingAs($this->chu)->patch("/quan-ly/tham-gia-keo/{$tg->id}/mien-phat")->assertSessionHasNoErrors();
        $this->assertSame(100, $tvB->fresh()->diem_uy_tin);
        $this->assertSame(0, $tvB->fresh()->so_tran_vang);
        $this->assertSame(90, $b->fresh()->diem_uy_tin);                // đại diện B vẫn bị trừ

        // Người lạ không điểm danh được
        $this->actingAs($this->khachCu())->post("/keo/{$k->id}/diem-danh", ['ben' => 'a', 'co_mat' => 0])->assertSessionHasErrors('keo');
    }

    public function test_ghep_le_tai_khoan_moi_chi_vao_keo_co_coc(): void
    {
        $this->actingAs($this->doiA)->post('/keo', [
            'ma_don' => $this->don->ma_don, 'loai' => 'ghep_le', 'so_nguoi_can' => 1,
            'tien_moi_nguoi' => 50000, 'tien_coc_ca_nhan' => 0, 'trinh_do' => 'trung_binh',
        ])->assertSessionHasNoErrors();
        $k = Keo::firstOrFail();

        $moi = User::factory()->create();                                 // vừa tạo, 0 trận
        $this->actingAs($moi)->post("/keo/{$k->id}/tham-gia")->assertSessionHasErrors('keo');

        $cu = $this->khachCu();
        $this->actingAs($cu)->post("/keo/{$k->id}/tham-gia")->assertSessionHasNoErrors();
        $this->assertSame('da_chot', $k->fresh()->trang_thai);           // đủ 1 người

        $this->actingAs($this->khachCu())->post("/keo/{$k->id}/tham-gia")->assertSessionHasErrors('keo');
    }

    public function test_ghep_le_co_coc_qua_han_thi_nha_cho(): void
    {
        $this->actingAs($this->doiA)->post('/keo', [
            'ma_don' => $this->don->ma_don, 'loai' => 'ghep_le', 'so_nguoi_can' => 1,
            'tien_moi_nguoi' => 50000, 'tien_coc_ca_nhan' => 20000, 'trinh_do' => 'kha',
        ]);
        $k = Keo::firstOrFail();
        $moi = User::factory()->create();                                 // tài khoản mới vẫn vào được kèo CÓ cọc
        $this->actingAs($moi)->post("/keo/{$k->id}/tham-gia")->assertRedirect();
        $this->assertSame('cho_coc', ThamGiaKeo::where('user_id', $moi->id)->value('trang_thai'));

        $this->travel(16)->minutes();
        $this->artisan('keo:quet');
        $this->assertSame('bi_huy', ThamGiaKeo::where('user_id', $moi->id)->value('trang_thai'));
        $this->assertSame('het_han', GiaoDich::where('loai', 'coc_keo_le')->value('trang_thai'));

        // Vào lại được
        $this->actingAs($moi)->post("/keo/{$k->id}/tham-gia")->assertRedirect();
        $this->assertSame('cho_coc', ThamGiaKeo::where('user_id', $moi->id)->value('trang_thai'));
    }

    public function test_vang_3_lan_trong_30_ngay_bi_khoa_keo(): void
    {
        $u = $this->khachCu();
        for ($i = 0; $i < 3; $i++) {
            $tg = new ThamGiaKeo;
            $tg->forceFill(['keo_id' => $this->taoKeoGia()->id, 'user_id' => $u->id, 'ben' => 'a', 'vai_tro' => 'nguoi_le', 'trang_thai' => 'da_xac_nhan'])->save();
            $this->travelTo(Carbon::parse('2026-11-18 17:30'));
            app(KeoService::class)->diemDanhNguoi($tg, false, $this->chu);
            $this->travelTo(Carbon::parse('2026-11-18 08:00'));
        }

        $u->refresh();
        $this->assertSame(70, $u->diem_uy_tin);
        $this->assertTrue($u->khoa_keo_den->isFuture());
        $this->assertStringContainsString('vắng 3 lần', $u->lyDoKhongVaoKeo(true));
    }

    /** Kèo phụ trên các khung khác để thử nhiều lần vắng. */
    private function taoKeoGia(): Keo
    {
        static $gio = 9;
        $gio++;
        $don = app(DatSanService::class)->ghiDon($this->b, '2026-11-18', $gio * 60, ($gio + 1) * 60, [
            'khach_id' => $this->doiA->id, 'gia_chot' => 300000, 'tien_coc' => 0, 'nguon' => 'online', 'trang_thai' => 'da_dat',
        ]);
        $k = new Keo;
        $k->forceFill(['dat_san_id' => $don->id, 'loai' => 'ghep_le', 'nguoi_tao_id' => $this->doiA->id, 'tien_san' => 300000,
            'so_nguoi_can' => 1, 'trang_thai' => 'da_chot', 'han_xac_nhan_luc' => now()->addDay()])->save();

        return $k;
    }
}
