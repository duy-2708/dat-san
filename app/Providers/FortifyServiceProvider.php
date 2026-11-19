<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Fortify lo phần "lõi" xác thực (route, kiểm tra, gửi mail).
 * Ở đây ta chỉ nói cho Fortify: dùng view nào, tạo user bằng lớp nào, kiểm tra đăng nhập ra sao.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Giao diện Bootstrap của mình
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        // Tự kiểm tra đăng nhập để chặn tài khoản bị khoá
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', Str::lower(trim((string) $request->input('email'))))->first();

            if (! $user || ! $user->password || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null; // Fortify tự báo "Thông tin đăng nhập không đúng"
            }

            if ($user->biKhoa()) {
                throw ValidationException::withMessages([
                    Fortify::username() => 'Tài khoản đã bị khoá. Vui lòng liên hệ quản trị viên.',
                ]);
            }

            return $user;
        });

        // Tối đa 5 lần đăng nhập/phút cho mỗi email + IP
        RateLimiter::for('login', function (Request $request) {
            $khoa = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($khoa);
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }
}
