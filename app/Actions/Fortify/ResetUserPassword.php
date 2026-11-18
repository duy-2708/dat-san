<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function reset(User $user, array $input): void
    {
        Validator::make($input, ['password' => $this->passwordRules()])->validate();

        $user->forceFill(['password' => $input['password']]);
        // Bấm được link trong email nghĩa là đúng chủ hộp thư → coi như đã xác thực email
        if (! $user->hasVerifiedEmail()) {
            $user->email_verified_at = now();
        }
        $user->save();
    }
}
