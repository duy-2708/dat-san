<?php

namespace Tests\Feature\GiaiDoan1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleDangNhapTest extends TestCase
{
    use RefreshDatabase;

    /** Giả lập Google trả về một người dùng (không gọi Google thật). */
    private function giaLapGoogle(string $email, string $id = 'google-123'): void
    {
        $gUser = (new GoogleUser)->map([
            'id' => $id, 'name' => 'Cầu Thủ Google', 'email' => $email, 'avatar' => 'https://example.com/a.jpg',
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($gUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_tao_khach_moi_da_xac_thuc_va_bat_bo_sung_sdt(): void
    {
        $this->giaLapGoogle('moi@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect(route('bang-dieu-khien'));

        $u = User::where('email', 'moi@gmail.com')->firstOrFail();
        $this->assertSame(User::KHACH, $u->vai_tro);
        $this->assertNotNull($u->email_verified_at);
        $this->assertSame('google-123', $u->google_id);

        // Chưa có SĐT → bị đưa sang trang bổ sung
        $this->get('/bang-dieu-khien')->assertRedirect(route('ho-so.so-dien-thoai'));
        $this->post('/ho-so/so-dien-thoai', ['so_dien_thoai' => '0933 444 555'])->assertRedirect(route('bang-dieu-khien'));
        $this->assertSame('0933444555', $u->fresh()->so_dien_thoai);
    }

    public function test_tai_khoan_chua_xac_thuc_bi_google_thu_hoi(): void
    {
        $keChiem = User::factory()->chuaXacThuc()->create(['email' => 'that@gmail.com', 'so_dien_thoai' => '0911222333']);
        $this->giaLapGoogle('that@gmail.com');

        $this->get('/auth/google/callback');

        $u = $keChiem->fresh();
        $this->assertNotNull($u->email_verified_at);
        $this->assertNull($u->password);
        $this->assertNull($u->so_dien_thoai);
    }

    public function test_tai_khoan_da_xac_thuc_duoc_lien_ket_giu_nguyen_du_lieu(): void
    {
        $cu = User::factory()->create(['email' => 'cu@gmail.com', 'so_dien_thoai' => '0911222444']);
        $this->giaLapGoogle('cu@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect(route('bang-dieu-khien'));

        $this->assertAuthenticatedAs($cu);
        $this->assertSame('0911222444', $cu->fresh()->so_dien_thoai);
        $this->assertNotNull($cu->fresh()->password);
    }

    public function test_tai_khoan_bi_khoa_khong_vao_duoc_bang_google(): void
    {
        User::factory()->biKhoa()->create(['email' => 'khoa@gmail.com']);
        $this->giaLapGoogle('khoa@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
