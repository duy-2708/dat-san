<?php

namespace App\Actions\Fortify;

use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /** Mật khẩu tối thiểu 8 ký tự, phải nhập lại khớp (password_confirmation). */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::min(8), 'confirmed'];
    }
}
