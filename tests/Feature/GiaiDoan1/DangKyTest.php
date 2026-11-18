<?php

namespace Tests\Feature\GiaiDoan1;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DangKyTest extends TestCase
{
    use RefreshDatabase;

    private function duLieu(array $ghiDe = []): array
    {
        return array_merge([
            'name' => 'Nguyễn Văn A',
            'email' => 'NguyenVanA@Example.com',
            'so_dien_thoai' => '+84 901 234 567',
            'password' => 'matkhau123',
            'password_confirmation' => 'matkhau123',
        ], $ghiDe);
    }

    public function test_khach_dang_ky_thanh_cong_va_du_lieu_duoc_chuan_hoa(): void
    {
        Notification::fake();

        $this->post('/register', $this->duLieu())->assertRedirect('/bang-dieu-khien');

        $user = User::where('email', 'nguyenvana@example.com')->firstOrFail();
        $this->assertSame(User::KHACH, $user->vai_tro);
        $this->assertSame('0901234567', $user->so_dien_thoai);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_khong_tu_nang_quyen_qua_form_dang_ky(): void
    {
        $this->post('/register', $this->duLieu(['vai_tro' => 'admin', 'trang_thai' => 'hoat_dong']));

        $this->assertSame(User::KHACH, User::where('email', 'nguyenvana@example.com')->value('vai_tro'));
    }

    public function test_chua_xac_thuc_email_thi_khong_vao_duoc_trang_trong(): void
    {
        $user = User::factory()->chuaXacThuc()->create();

        $this->actingAs($user)->get('/bang-dieu-khien')->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get('/tai-khoan')->assertRedirect(route('verification.notice'));
    }

    public function test_so_dien_thoai_trung_bi_tu_choi_ke_ca_khac_cach_viet(): void
    {
        User::factory()->create(['so_dien_thoai' => '0901234567']);

        $this->post('/register', $this->duLieu(['so_dien_thoai' => '0901.234.567']))
            ->assertSessionHasErrors('so_dien_thoai');

        $this->assertSame(1, User::count());
    }

    public function test_email_bien_the_gmail_bi_tu_choi(): void
    {
        User::factory()->create(['email' => 'cauthu@gmail.com']);

        $this->post('/register', $this->duLieu(['email' => 'cau.thu+keo@gmail.com']))
            ->assertSessionHasErrors('email_chuan_hoa');
    }

    public function test_so_dien_thoai_sai_dinh_dang(): void
    {
        $this->post('/register', $this->duLieu(['so_dien_thoai' => '0123']))
            ->assertSessionHasErrors('so_dien_thoai');
    }

    public function test_mat_khau_ngan_bi_tu_choi(): void
    {
        $this->post('/register', $this->duLieu(['password' => '123', 'password_confirmation' => '123']))
            ->assertSessionHasErrors('password');
    }
}
