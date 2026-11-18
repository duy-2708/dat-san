<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\ChuanHoa;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Fortify gọi lớp này khi có người gửi form /register.
 * Chỉ tạo được tài khoản KHÁCH. Chủ sân do admin tạo, nhân viên do chủ sân tạo.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        $input['email'] = mb_strtolower(trim($input['email'] ?? ''));
        $input['so_dien_thoai'] = ChuanHoa::soDienThoai($input['so_dien_thoai'] ?? null);
        $input['email_chuan_hoa'] = ChuanHoa::email($input['email']);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'email_chuan_hoa' => ['unique:users,email_chuan_hoa'],
            'so_dien_thoai' => ['required', 'regex:'.ChuanHoa::REGEX_SDT, 'unique:users,so_dien_thoai'],
            'password' => $this->passwordRules(),
        ], [
            'email_chuan_hoa.unique' => 'Email này (hoặc một biến thể của nó) đã có tài khoản.',
            'so_dien_thoai.regex' => 'Số điện thoại di động không hợp lệ (VD 0901234567).',
            'so_dien_thoai.unique' => 'Số điện thoại này đã có tài khoản.',
        ])->validate();

        $user = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'so_dien_thoai' => $input['so_dien_thoai'],
            'password' => $input['password'], // cast 'hashed' tự băm
        ]);
        $user->vai_tro = User::KHACH;
        $user->save();

        return $user;
    }
}
