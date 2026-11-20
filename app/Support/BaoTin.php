<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ThongBao;
use App\Notifications\ThongBaoEmail;
use Illuminate\Support\Collection;

class BaoTin
{
    public static function gui(mixed $nguoi, string $tieuDe, string $noiDung, ?string $url = null, string $icon = 'bell', bool $email = false): void
    {
        $ds = collect($nguoi instanceof Collection ? $nguoi->all() : (is_array($nguoi) ? $nguoi : [$nguoi]))
            ->map(fn ($u) => $u instanceof User ? $u : ($u ? User::find($u) : null))
            ->filter(fn ($u) => $u && $u->trang_thai !== 'bi_khoa')
            ->unique('id');

        foreach ($ds as $u) {
            rescue(fn () => $u->notify(new ThongBao($tieuDe, $noiDung, $url, $icon)), report: false);
            if ($email && $u->email_verified_at) {
                rescue(fn () => $u->notify(new ThongBaoEmail($tieuDe, $noiDung, $url)), report: false);
            }
        }
    }

    public static function phiaSan(?int $chuSanId): Collection
    {
        return $chuSanId ? User::where('id', $chuSanId)->orWhere('chu_san_id', $chuSanId)->get() : collect();
    }

    public static function admins(): Collection
    {
        return User::where('vai_tro', User::ADMIN)->get();
    }
}
