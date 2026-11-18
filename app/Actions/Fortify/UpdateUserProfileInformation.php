<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * Cập nhật tên & vị trí sở trường. Email và SĐT KHÔNG sửa ở đây:
 * đổi email/SĐT ảnh hưởng chống tài khoản ảo nên sẽ có luồng riêng (xác thực lại).
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'vi_tri_so_truong' => ['nullable', 'in:thu_mon,hau_ve,tien_ve,tien_dao'],
        ])->validateWithBag('updateProfileInformation');

        $user->fill([
            'name' => $input['name'],
            'vi_tri_so_truong' => $input['vi_tri_so_truong'] ?? null,
        ])->save();
    }
}
