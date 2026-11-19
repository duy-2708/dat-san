<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Tạo (hoặc nâng quyền) tài khoản admin trên máy chủ thật — KHÔNG dùng tài khoản mẫu admin@datsan.test.
 * Chạy: php artisan tao-admin email@cua-ban.com   (hỏi tên, SĐT, mật khẩu; mật khẩu không hiện khi gõ)
 */
class TaoAdmin extends Command
{
    protected $signature = 'tao-admin {email}';

    protected $description = 'Tạo tài khoản admin (mật khẩu mạnh, email đã xác thực)';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $ten = $this->ask('Tên hiển thị', 'Quản trị hệ thống');
        $sdt = $this->ask('Số điện thoại (10 số)');
        $mk = $this->secret('Mật khẩu (≥ 12 ký tự, có chữ và số)');
        $mk2 = $this->secret('Nhập lại mật khẩu');

        $v = Validator::make(['email' => $email, 'so_dien_thoai' => $sdt, 'password' => $mk, 'password_confirmation' => $mk2], [
            'email' => ['required', 'email'],
            'so_dien_thoai' => ['required', 'regex:/^0\d{9}$/'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $loi) {
                $this->error($loi);
            }

            return self::FAILURE;
        }

        $u = User::firstOrNew(['email' => $email]);
        $u->fill(['name' => $ten, 'so_dien_thoai' => $sdt]);
        $u->forceFill(['password' => $mk, 'vai_tro' => User::ADMIN, 'trang_thai' => 'hoat_dong', 'email_verified_at' => now()])->save();
        $this->info("Đã tạo admin {$email}. Đăng nhập rồi vào menu tên → Bảo mật tài khoản để bật xác minh 2 bước (bắt buộc).");

        return self::SUCCESS;
    }
}
