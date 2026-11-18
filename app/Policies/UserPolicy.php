<?php

namespace App\Policies;

use App\Models\User;

/**
 * Laravel tự tìm Policy theo tên: model User → App\Policies\UserPolicy.
 * Gọi trong controller: Gate::authorize('quanLyNhanVien', $nhanVien);
 */
class UserPolicy
{
    /** Chủ sân chỉ quản lý nhân viên CỦA MÌNH. */
    public function quanLyNhanVien(User $nguoiDung, User $nhanVien): bool
    {
        return $nguoiDung->laChuSan()
            && $nhanVien->laNhanVien()
            && $nhanVien->chu_san_id === $nguoiDung->id;
    }

    /** Admin khoá/mở mọi tài khoản trừ admin khác (và chính mình). */
    public function khoaTaiKhoan(User $nguoiDung, User $doiTuong): bool
    {
        return $nguoiDung->laAdmin() && ! $doiTuong->laAdmin();
    }
}
