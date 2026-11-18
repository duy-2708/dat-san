<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

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
