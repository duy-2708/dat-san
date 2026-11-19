<?php

/*
| Cấu hình bảo mật & vận hành. Đổi trong .env, không sửa file này.
*/
return [
    // Giới hạn tốc độ gửi form (chống spam, dò mật khẩu). Test tự tắt (phpunit.xml).
    'gioi_han' => (bool) env('GIOI_HAN_TOC_DO', true),
    'gui_moi_phut' => (int) env('GIOI_HAN_GUI_MOI_PHUT', 40),      // mỗi tài khoản / IP, mọi thao tác ghi
    'nhay_cam_10_phut' => (int) env('GIOI_HAN_NHAY_CAM', 5),       // đăng ký, quên mật khẩu, gửi lại mail xác thực / IP

    // Admin bắt buộc bật xác minh 2 bước (mặc định: chỉ bắt khi chạy production)
    'admin_2fa' => (bool) env('BAT_BUOC_2FA_ADMIN', env('APP_ENV') === 'production'),

    // Sao lưu CSDL: giữ bao nhiêu ngày trong storage/app/sao-luu
    'sao_luu_giu_ngay' => (int) env('SAO_LUU_GIU_NGAY', 14),

    // Nhật ký thao tác: giữ bao nhiêu ngày
    'nhat_ky_giu_ngay' => (int) env('NHAT_KY_GIU_NGAY', 180),
];
