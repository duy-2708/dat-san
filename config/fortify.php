<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,

    // Sau khi đăng nhập/đăng ký → về đây, BangDieuKhienController chia theo vai trò
    'home' => '/bang-dieu-khien',

    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],

    // true: Fortify đăng ký route GET hiển thị view (login, register...) dùng view ta khai báo trong FortifyServiceProvider
    'views' => true,

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        // Xác minh 2 bước bằng app (Google Authenticator / Microsoft Authenticator). Bắt buộc với admin khi BAT_BUOC_2FA_ADMIN=true
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
    ],
];
