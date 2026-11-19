<?php

namespace App\Policies;

use App\Models\San;
use App\Models\User;

/**
 * Quyền trên TỪNG sân. Chủ sân: toàn quyền với sân của mình.
 * Nhân viên: chỉ xem sân của chủ mình (để đánh dấu đặt / xác nhận cọc ở giai đoạn 3).
 */
class SanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->coVaiTro(User::CHU_SAN, User::NHAN_VIEN);
    }

    public function view(User $user, San $san): bool
    {
        return $user->idChuSanQuanLy() !== null && (int) $san->chu_san_id === $user->idChuSanQuanLy();
    }

    public function create(User $user): bool
    {
        return $user->laChuSan();
    }

    public function update(User $user, San $san): bool
    {
        return $user->laChuSan() && (int) $san->chu_san_id === $user->id;
    }

    public function delete(User $user, San $san): bool
    {
        return $this->update($user, $san);
    }
}
