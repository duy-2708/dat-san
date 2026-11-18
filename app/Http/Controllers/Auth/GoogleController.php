<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ChuanHoa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class GoogleController extends Controller
{
    public function chuyenHuong(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function xuLy(Request $request): RedirectResponse
    {
        try {
            $g = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors(['email' => 'Đăng nhập Google thất bại, vui lòng thử lại.']);
        }

        $email = Str::lower((string) $g->getEmail());
        if ($email === '') {
            return redirect()->route('login')->withErrors(['email' => 'Tài khoản Google không cung cấp email.']);
        }

        $user = User::where('google_id', $g->getId())->first()
            ?? User::where('email', $email)->first();

        if ($user) {
            if ($user->biKhoa()) {
                return redirect()->route('login')->withErrors(['email' => 'Tài khoản đã bị khoá.']);
            }

            if (! $user->hasVerifiedEmail()) {
                // Tài khoản cũ chưa xác thực có thể do người khác "chiếm" email này.
                // Google chứng minh người đang đăng nhập là chủ hộp thư → xoá mật khẩu & SĐT cũ.
                $user->password = null;
                $user->so_dien_thoai = null;
                $user->email_verified_at = now();
            }
            $user->google_id ??= $g->getId();
            $user->anh_dai_dien ??= $g->getAvatar();
            $user->save();
        } else {
            if (User::where('email_chuan_hoa', ChuanHoa::email($email))->exists()) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Đã có tài khoản dùng biến thể của email này. Hãy đăng nhập bằng email đó.',
                ]);
            }

            $user = new User([
                'name' => $g->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'anh_dai_dien' => $g->getAvatar(),
            ]);
            $user->google_id = $g->getId();
            $user->vai_tro = User::KHACH;
            $user->email_verified_at = now(); // Google đã xác thực email
            $user->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('bang-dieu-khien'));
    }
}
