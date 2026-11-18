<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function update(User $user, array $input): void
    {
        $quyTac = ['password' => $this->passwordRules()];
        // Tài khoản Google chưa có mật khẩu thì không hỏi mật khẩu cũ
        if ($user->password) {
            $quyTac['current_password'] = ['required', 'string', 'current_password:web'];
        }

        Validator::make($input, $quyTac, [
            'current_password.current_password' => 'Mật khẩu hiện tại không đúng.',
        ])->validateWithBag('updatePassword');

        $user->forceFill(['password' => $input['password']])->save();
    }
}
