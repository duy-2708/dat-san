<?php

namespace Tests\Feature\GiaiDoan10;

use App\Models\NhatKy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BaoMatTest extends TestCase
{
    use RefreshDatabase;

    public function test_moi_trang_co_header_bao_mat(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('X-Powered-By');
        $this->get('/up')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_dang_ky_lien_tuc_bi_chan_429(): void
    {
        config(['bao_mat.gioi_han' => true, 'bao_mat.nhay_cam_10_phut' => 2]);
        $this->post('/register', [])->assertStatus(302);
        $this->post('/register', [])->assertStatus(302);
        $this->post('/register', [])->assertStatus(429)->assertSee('Thao tác quá nhanh');
    }

    public function test_thao_tac_ghi_qua_nhanh_bi_chan_theo_tai_khoan(): void
    {
        config(['bao_mat.gioi_han' => true, 'bao_mat.gui_moi_phut' => 3]);
        $u = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($u)->post('/ho-so/so-dien-thoai', [])->assertStatus(302);
        }
        $this->actingAs($u)->post('/ho-so/so-dien-thoai', [])->assertStatus(429);
        // Người khác không bị ảnh hưởng
        $this->actingAs(User::factory()->create())->post('/ho-so/so-dien-thoai', [])->assertStatus(302);
    }

    public function test_admin_chua_bat_2fa_bi_buoc_bat_khi_cau_hinh_yeu_cau(): void
    {
        config(['bao_mat.admin_2fa' => true]);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/chu-san')->assertRedirect(route('bao-mat'));

        $admin->forceFill(['two_factor_secret' => encrypt('ABCDEFGHIJKLMNOP'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin->fresh())->get('/admin/chu-san')->assertOk();
    }

    public function test_bat_2fa_va_dang_nhap_bang_ma_du_phong(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/tai-khoan/bao-mat')->assertRedirect(route('password.confirm'));

        $phien = ['auth.password_confirmed_at' => time()];
        $this->actingAs($u)->withSession($phien)->get('/tai-khoan/bao-mat')->assertOk()->assertSee('Bật xác minh 2 bước');
        $this->actingAs($u)->withSession($phien)->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
        $u->refresh();
        $this->assertNotNull($u->two_factor_secret);
        $this->actingAs($u)->withSession($phien)->get('/tai-khoan/bao-mat')->assertOk()->assertSee('<svg', false);

        $ma = app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp(decrypt($u->two_factor_secret));
        $this->actingAs($u)->withSession($phien)->post('/user/confirmed-two-factor-authentication', ['code' => $ma])->assertSessionHasNoErrors();
        $u->refresh();
        $this->assertNotNull($u->two_factor_confirmed_at);
        $duPhong = $u->recoveryCodes()[0];

        // Đăng nhập lại: mật khẩu đúng vẫn phải qua bước mã
        $this->post('/logout');
        $this->assertGuest();
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['recovery_code' => $duPhong]);
        $this->assertAuthenticatedAs($u);
        $this->assertNotContains($duPhong, $u->fresh()->recoveryCodes());
    }

    public function test_nhat_ky_ghi_dang_nhap_sai_va_thao_tac_admin(): void
    {
        $khach = User::factory()->create();
        $this->post('/login', ['email' => $khach->email, 'password' => 'sai-mat-khau']);
        $this->assertTrue(NhatKy::where('hanh_dong', 'dang_nhap_sai')->where('user_id', $khach->id)->exists());

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch("/admin/tai-khoan/{$khach->id}/khoa", ['ly_do_khoa' => 'spam'])->assertSessionHasNoErrors();
        $nk = NhatKy::where('hanh_dong', 'admin.tai-khoan.khoa')->firstOrFail();
        $this->assertSame($admin->id, $nk->user_id);
        $this->assertStringContainsString('user#'.$khach->id, $nk->chi_tiet);
        $this->assertStringNotContainsString('spam', $nk->chi_tiet); // không lưu nội dung form

        $this->actingAs($admin)->get('/admin/nhat-ky?hanh_dong=admin.')->assertOk()->assertSee('admin.tai-khoan.khoa');
        $this->actingAs(User::factory()->chuSan()->create())->get('/admin/nhat-ky')->assertForbidden();
    }

    public function test_trang_loi_tieng_viet_khong_lo_ky_thuat(): void
    {
        $this->get('/duong-dan-khong-co')->assertNotFound()->assertSee('Không tìm thấy trang');
        $this->actingAs(User::factory()->create())->get('/admin/chu-san')->assertForbidden()->assertSee('Không có quyền truy cập');
    }

    public function test_sao_luu_don_ban_cu(): void
    {
        $thuMuc = storage_path('app/sao-luu');
        File::ensureDirectoryExists($thuMuc);
        $cu = $thuMuc.'/dat_san-test-cu.sql.gz';
        $moi = $thuMuc.'/dat_san-test-moi.sql.gz';
        File::put($cu, 'x');
        File::put($moi, 'x');
        touch($cu, now()->subDays(30)->getTimestamp());

        $this->artisan('sao-luu:csdl', ['--chi-don' => true])->assertSuccessful();
        $this->assertFileDoesNotExist($cu);
        $this->assertFileExists($moi);
        File::delete($moi);
    }

    public function test_lenh_tao_admin_bat_mat_khau_manh(): void
    {
        $this->artisan('tao-admin', ['email' => 'Duy@Example.com'])
            ->expectsQuestion('Tên hiển thị', 'Duy')->expectsQuestion('Số điện thoại (10 số)', '0912345678')
            ->expectsQuestion('Mật khẩu (≥ 12 ký tự, có chữ và số)', 'ngan')->expectsQuestion('Nhập lại mật khẩu', 'ngan')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'duy@example.com']);

        $this->artisan('tao-admin', ['email' => 'Duy@Example.com'])
            ->expectsQuestion('Tên hiển thị', 'Duy')->expectsQuestion('Số điện thoại (10 số)', '0912345678')
            ->expectsQuestion('Mật khẩu (≥ 12 ký tự, có chữ và số)', 'MatKhauRatManh2026')->expectsQuestion('Nhập lại mật khẩu', 'MatKhauRatManh2026')
            ->assertSuccessful();
        $u = User::where('email', 'duy@example.com')->firstOrFail();
        $this->assertTrue($u->laAdmin());
        $this->assertNotNull($u->email_verified_at);
    }
}
