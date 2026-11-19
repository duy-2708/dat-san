<?php

namespace App\Policies;

use App\Models\DatSan;
use App\Models\User;

class DatSanPolicy
{
    /** Khách xem đơn của chính mình; chủ sân/nhân viên xem đơn ở sân của mình. */
    public function view(User $user, DatSan $don): bool
    {
        return $this->laKhachCuaDon($user, $don) || $this->quanLy($user, $don);
    }

    /** Chủ sân + nhân viên: xác nhận cọc, gia hạn giữ chỗ. */
    public function quanLy(User $user, DatSan $don): bool
    {
        return $user->idChuSanQuanLy() !== null && (int) $don->san->chu_san_id === $user->idChuSanQuanLy();
    }

    /** Huỷ: khách huỷ đơn của mình; phía sân chỉ CHỦ SÂN được huỷ (nhân viên không). */
    public function huy(User $user, DatSan $don): bool
    {
        return $this->laKhachCuaDon($user, $don)
            || ($user->laChuSan() && (int) $don->san->chu_san_id === $user->id);
    }

    public function xacNhanHoan(User $user, DatSan $don): bool
    {
        return $user->laChuSan() && (int) $don->san->chu_san_id === $user->id;
    }

    private function laKhachCuaDon(User $user, DatSan $don): bool
    {
        return $don->khach_id !== null && (int) $don->khach_id === $user->id;
    }
}
