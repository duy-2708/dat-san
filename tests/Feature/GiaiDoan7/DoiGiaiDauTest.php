<?php

namespace Tests\Feature\GiaiDoan7;

use App\Models\Doi;
use App\Models\DoiGiaiDau;
use App\Models\GiaiDau;
use App\Models\ThanhVienDoi;
use App\Models\TranDau;
use App\Models\User;
use App\Services\GiaiDauService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DoiGiaiDauTest extends TestCase
{
    use RefreshDatabase;

    private function taoDoi(User $dt, string $ten): Doi
    {
        $this->actingAs($dt)->post('/doi', ['ten' => $ten])->assertSessionHasNoErrors();

        return Doi::where('ten', $ten)->firstOrFail();
    }

    public function test_tao_doi_co_logo_va_thanh_vien(): void
    {
        Storage::fake('public');
        $dt = User::factory()->create();

        $this->actingAs($dt)->post('/doi', ['ten' => 'FC Test', 'logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
            ->assertRedirect();

        $doi = Doi::firstOrFail();
        Storage::disk('public')->assertExists($doi->logo);
        $this->assertTrue($doi->laDoiTruong($dt));
        $this->get('/doi/'.$doi->slug)->assertOk()->assertSee('FC Test')->assertSee($dt->name);

        // Đội trưởng thêm bằng SĐT
        $tv = User::factory()->create(['so_dien_thoai' => '0933111222']);
        $this->actingAs($dt)->post("/doi/{$doi->slug}/thanh-vien", ['tim' => '0933 111 222', 'so_ao' => 10])->assertSessionHasNoErrors();
        $this->assertTrue($doi->laThanhVien($tv));
    }

    public function test_xin_vao_doi_va_doi_truong_duyet(): void
    {
        $dt = User::factory()->create();
        $doi = $this->taoDoi($dt, 'FC Xin Vào');
        $u = User::factory()->create();

        $this->actingAs($u)->post("/doi/{$doi->slug}/xin-vao")->assertSessionHasNoErrors();
        $tv = ThanhVienDoi::where('user_id', $u->id)->firstOrFail();
        $this->assertSame('cho_duyet', $tv->trang_thai);

        // Người khác không duyệt được
        $this->actingAs(User::factory()->create())->patch("/thanh-vien-doi/{$tv->id}/duyet", ['dong_y' => 1])->assertSessionHasErrors('doi');

        $this->actingAs($dt)->patch("/thanh-vien-doi/{$tv->id}/duyet", ['dong_y' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($doi->laThanhVien($u));

        // Tự rời đội được; đội trưởng không tự rời
        $this->actingAs($u)->delete("/thanh-vien-doi/{$tv->id}")->assertSessionHasNoErrors();
        $this->assertFalse($doi->laThanhVien($u));
        $tvDt = ThanhVienDoi::where('user_id', $dt->id)->firstOrFail();
        $this->actingAs($dt)->delete("/thanh-vien-doi/{$tvDt->id}")->assertSessionHasErrors('doi');
    }

    public function test_lich_vong_tron_moi_cap_gap_nhau_dung_mot_lan(): void
    {
        foreach ([[1, 2, 3, 4], [1, 2, 3, 4, 5], [1, 2, 3, 4, 5, 6]] as $ids) {
            $lich = GiaiDauService::lichVongTron($ids);
            $n = count($ids);
            $this->assertCount($n * ($n - 1) / 2, $lich);
            $cap = collect($lich)->map(fn ($t) => min($t[1], $t[2]).'-'.max($t[1], $t[2]));
            $this->assertSame($cap->count(), $cap->unique()->count());
        }
    }

    public function test_quy_trinh_giai_dau_den_thanh_tich_tren_ho_so(): void
    {
        $chu = User::factory()->chuSan()->create();
        $this->actingAs($chu)->post('/quan-ly/giai-dau', [
            'ten' => 'Cúp Test', 'loai_san' => 7, 'the_thuc' => 'vong_tron',
            'tu_ngay' => today()->toDateString(), 'den_ngay' => today()->addDays(7)->toDateString(), 'so_doi_toi_da' => 4,
        ])->assertSessionHasNoErrors();
        $g = GiaiDau::firstOrFail();

        // 3 đội đăng ký
        $dts = [];
        foreach (['Đội A', 'Đội B', 'Đội C'] as $ten) {
            $dt = User::factory()->create();
            $doi = $this->taoDoi($dt, $ten);
            $this->actingAs($dt)->post("/giai-dau/{$g->slug}/dang-ky", ['doi_id' => $doi->id, 'cau_thu' => [$dt->id]])->assertSessionHasNoErrors();
            $dts[$ten] = $dt;
        }
        // Không đăng ký hộ được đội người khác / cầu thủ ngoài đội
        $la = User::factory()->create();
        $this->actingAs($la)->post("/giai-dau/{$g->slug}/dang-ky", ['doi_id' => Doi::first()->id, 'cau_thu' => [$la->id]])->assertSessionHasErrors('giai');

        foreach (DoiGiaiDau::all() as $dk) {
            $this->actingAs($chu)->patch("/quan-ly/dang-ky-giai/{$dk->id}", ['dong_y' => 1])->assertSessionHasNoErrors();
        }
        $this->actingAs($chu)->patch("/quan-ly/giai-dau/{$g->slug}/dong-dang-ky")->assertSessionHasNoErrors();
        $this->assertSame(3, TranDau::count());                       // 3 đội vòng tròn → 3 trận

        // A thắng cả 2, B thắng C
        $id = fn ($ten) => Doi::where('ten', $ten)->value('id');
        foreach (TranDau::all() as $t) {
            $doiThang = in_array($id('Đội A'), [$t->doi_nha_id, $t->doi_khach_id]) ? $id('Đội A') : $id('Đội B');
            [$n, $k] = $t->doi_nha_id === $doiThang ? [2, 0] : [0, 2];
            $this->actingAs($chu)->patch("/quan-ly/tran-dau/{$t->id}", ['ban_thang_nha' => $n, 'ban_thang_khach' => $k])->assertSessionHasNoErrors();
        }
        $bxh = app(GiaiDauService::class)->bangXepHang($g->fresh());
        $this->assertSame(['Đội A', 'Đội B', 'Đội C'], $bxh->pluck('doi.ten')->all());
        $this->assertSame(6, $bxh[0]['diem']);

        $dkA = DoiGiaiDau::where('doi_id', $id('Đội A'))->first();
        $this->actingAs($chu)->patch("/quan-ly/giai-dau/{$g->slug}/ket-thuc", [
            'xep_hang' => [$dkA->id => ['thu_hang' => 1, 'danh_hieu' => 'Vô địch']],
        ])->assertSessionHasNoErrors();

        // Thành tích hiện trên hồ sơ cầu thủ và trang đội
        $this->get('/cau-thu/'.$dts['Đội A']->id)->assertOk()->assertSee('Cúp Test')->assertSee('Vô địch');
        $this->get('/doi/'.Doi::find($id('Đội A'))->slug)->assertSee('Vô địch');
        $this->get('/giai-dau/'.$g->slug)->assertOk()->assertSee('Đội C');

        // Rời đội sau giải vẫn giữ thành tích
        $tv = ThanhVienDoi::where('user_id', $dts['Đội A']->id)->first();
        $tv->forceFill(['trang_thai' => 'da_roi'])->save();
        $this->assertCount(1, app(GiaiDauService::class)->thanhTichCauThu($dts['Đội A']));
    }

    public function test_chu_san_khac_khong_quan_ly_duoc_giai(): void
    {
        $chu = User::factory()->chuSan()->create();
        $g = new GiaiDau;
        $g->forceFill(['chu_san_id' => $chu->id, 'ten' => 'G', 'slug' => 'g-x', 'loai_san' => 7, 'the_thuc' => 'vong_tron',
            'tu_ngay' => today(), 'den_ngay' => today(), 'so_doi_toi_da' => 4, 'trang_thai' => 'mo_dang_ky'])->save();

        $khac = User::factory()->chuSan()->create();
        $this->actingAs($khac)->get('/quan-ly/giai-dau/g-x')->assertForbidden();
        $this->actingAs($khac)->patch('/quan-ly/giai-dau/g-x/dong-dang-ky')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/quan-ly/giai-dau/g-x')->assertForbidden();
    }

    public function test_sua_ho_so_cau_thu(): void
    {
        Storage::fake('public');
        $u = User::factory()->create();

        $this->actingAs($u)->put('/ho-so', ['name' => 'Tên Mới', 'vi_tri_so_truong' => 'thu_mon', 'anh' => UploadedFile::fake()->image('a.jpg')])
            ->assertRedirect("/cau-thu/{$u->id}");

        $u->refresh();
        $this->assertSame('Tên Mới', $u->name);
        $this->assertSame('thu_mon', $u->vi_tri_so_truong);
        Storage::disk('public')->assertExists($u->anh_dai_dien);
        $this->get("/cau-thu/{$u->id}")->assertSee('Thủ môn');

        $this->get('/cau-thu/'.User::factory()->chuSan()->create()->id)->assertNotFound(); // hồ sơ chỉ cho khách
    }
}
