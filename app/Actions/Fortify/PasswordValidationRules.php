<?php

namespace App\Actions\Fortify;

use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /** Mật khẩu theo Password::defaults() (AppServiceProvider): ≥ 8 ký tự; production thêm chữ + số và chặn mật khẩu đã bị lộ. */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::defaults(), 'confirmed'];
    }
}
