<?php

namespace Tests\Feature\GiaiDoan9;

use App\Models\DatSan;
use App\Models\User;
use App\Notifications\KhachBaoDaChuyen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\GiaiDoan3\CoSanDeDat;
use Tests\TestCase;

class ChongBungTest extends TestCase
{
    use CoSanDeDat;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();
    }

    public function test_bao_da_chuyen_mot_lan_giu_them_va_bao_email(): void
    {
        Notification::fake();
        $nv = User::factory()->nhanVienCua($this->chu)->create();
        $khach = User::factory()->create();
        $this->datOnline($khach, $this->a, '17:00');
        $don = DatSan::firstOrFail();

        $this->actingAs($khach)->post("/dat-san/{$don->ma_don}/da-chuyen")->assertSessionHasNoErrors();
        $don->refresh();
        $this->assertNotNull($don->bao_da_chuyen_luc);
        $this->assertTrue($don->giu_cho_den->gte(now()->addMinutes(119)));
        Notification::assertSentTo([$this->chu, $nv], KhachBaoDaChuyen::class);

        $this->actingAs($khach)->post("/dat-san/{$don->ma_don}/da-chuyen")->assertSessionHasErrors('dat_san');
        $this->actingAs($this->chu)->get('/quan-ly/dat-san')->assertSee('Khách báo đã chuyển');
    }

    public function test_khong_thay_tien_huy_don_tru_uy_tin_hai_lan_thi_cam(): void
    {
        $khach = User::factory()->create();
        foreach (['17:00', '19:00'] as $i => $gio) {
            $this->datOnline($khach, $this->a, $gio);
            $don = DatSan::latest('id')->firstOrFail();
            $this->actingAs($khach)->post("/dat-san/{$don->ma_don}/da-chuyen");
            $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$don->ma_don}/khong-thay-tien")->assertSessionHasNoErrors();
            $this->assertSame('da_huy', $don->fresh()->trang_thai);
        }
        $khach->refresh();
        $this->assertSame(90, $khach->diem_uy_tin);
        $this->assertTrue($khach->cam_dat_den->isFuture());
        $this->datOnline($khach, $this->a, '20:00')->assertSessionHasErrors('dat_san');
    }

    public function test_khong_thanh_toan_du_lan_1_cam_30_ngay_lan_2_khoa(): void
    {
        $khach = User::factory()->create();
        $nv = User::factory()->nhanVienCua($this->chu)->create();
        foreach (['09:00', '10:00'] as $gio) {
            $this->datOnline($khach, $this->a, $gio);
            $d = DatSan::latest('id')->firstOrFail();
            $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$d->ma_don}/xac-nhan-coc");
        }
        [$d1, $d2] = DatSan::orderBy('id')->get()->all();
        $this->travelTo(now()->setTime(11, 30));

        $this->actingAs($nv)->patch("/quan-ly/dat-san/{$d1->ma_don}/khong-tra-du")->assertForbidden();
        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$d1->ma_don}/khong-tra-du")->assertSessionHasNoErrors();
        $khach->refresh();
        $this->assertSame(80, $khach->diem_uy_tin);
        $this->assertTrue($khach->cam_dat_den->between(now()->addDays(29), now()->addDays(31)));
        $this->assertSame('hoat_dong', $khach->trang_thai);

        $this->actingAs($this->chu)->patch("/quan-ly/dat-san/{$d2->ma_don}/khong-tra-du")->assertSessionHasNoErrors();
        $this->assertSame('bi_khoa', $khach->fresh()->trang_thai);

        // Email / SĐT cũ không đăng ký lại được
        \Illuminate\Support\Facades\Auth::logout();
        $this->post('/register', ['name' => 'X', 'email' => $khach->email, 'so_dien_thoai' => '0988777666',
            'password' => 'matkhau123', 'password_confirmation' => 'matkhau123'])->assertSessionHasErrors('email');
    }
}
