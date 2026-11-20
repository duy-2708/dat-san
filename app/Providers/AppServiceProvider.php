<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\NhatKy;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(\App\Services\ViecCanLam::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // ---- Bảo mật khi chạy thật ----
        if ($this->app->isProduction()) {
            URL::forceScheme('https');              // mọi link đều https
        }
        DB::prohibitDestructiveCommands($this->app->isProduction()); // chặn migrate:fresh / db:wipe trên máy chủ thật
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()   // chặn mật khẩu đã lộ (haveibeenpwned, miễn phí)
            : Password::min(8));

        // ---- Nhật ký đăng nhập ----
        Event::listen(Login::class, fn (Login $e) => rescue(fn () => NhatKy::ghi('dang_nhap', request()?->userAgent(), $e->user->getAuthIdentifier()), report: false));
        Event::listen(Failed::class, fn (Failed $e) => rescue(fn () => NhatKy::ghi('dang_nhap_sai', 'Email: '.($e->credentials['email'] ?? '?'),
            $e->user?->getAuthIdentifier() ?? User::where('email', mb_strtolower(trim((string) ($e->credentials['email'] ?? ''))))->value('id')), report: false));
        Event::listen(Lockout::class, function (Lockout $e) {
            rescue(fn () => NhatKy::ghi('bi_chan_dang_nhap', 'Email: '.$e->request->input('email')), report: false);
            \App\Support\BaoTin::gui(\App\Support\BaoTin::admins(), 'Đăng nhập sai nhiều lần',
                'Email '.$e->request->input('email').' từ IP '.$e->request->ip().' bị chặn tạm vì sai mật khẩu liên tục.', route('admin.nhat-ky', ['hanh_dong' => 'bi_chan_dang_nhap']), 'shield-exclamation');
        });
        Event::listen(PasswordReset::class, fn (PasswordReset $e) => rescue(fn () => NhatKy::ghi('doi_mat_khau', null, $e->user->getAuthIdentifier()), report: false));

        // Email xác thực bằng tiếng Việt
        VerifyEmail::toMailUsing(fn ($notifiable, string $url) => (new MailMessage)
            ->subject('Xác thực email — '.config('app.name'))
            ->greeting('Chào '.$notifiable->name.',')
            ->line('Bấm nút dưới đây để xác thực địa chỉ email và bắt đầu đặt sân.')
            ->action('Xác thực email', $url)
            ->line('Nếu bạn không tạo tài khoản, hãy bỏ qua email này.')
            ->salutation('Trân trọng, '.config('app.name')));

        ResetPassword::toMailUsing(fn ($notifiable, string $token) => (new MailMessage)
            ->subject('Đặt lại mật khẩu — '.config('app.name'))
            ->greeting('Chào '.$notifiable->name.',')
            ->line('Bạn (hoặc ai đó) vừa yêu cầu đặt lại mật khẩu.')
            ->action('Đặt mật khẩu mới', url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false)))
            ->line('Liên kết hết hạn sau '.config('auth.passwords.users.expire').' phút. Nếu không phải bạn, hãy bỏ qua email này.')
            ->salutation('Trân trọng, '.config('app.name')));
    }
}
