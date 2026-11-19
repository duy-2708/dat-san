<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nhật ký thao tác — tra soát khi có tranh chấp tiền cọc, khoá tài khoản, đổi gói... */
class NhatKy extends Model
{
    use MassPrunable;

    protected $table = 'nhat_ky';

    protected $guarded = ['*'];

    public const UPDATED_AT = null;

    public const NHAN = [
        'dang_nhap' => 'Đăng nhập',
        'dang_nhap_sai' => 'Đăng nhập sai',
        'bi_chan_dang_nhap' => 'Bị chặn đăng nhập (sai quá nhiều)',
        'doi_mat_khau' => 'Đặt lại mật khẩu',
    ];

    public static function ghi(string $hanhDong, ?string $chiTiet = null, ?int $userId = null): void
    {
        (new static)->forceFill([
            'user_id' => $userId,
            'hanh_dong' => mb_substr($hanhDong, 0, 80),
            'chi_tiet' => $chiTiet !== null ? mb_substr($chiTiet, 0, 500) : null,
            'ip' => request()?->ip(),
        ])->save();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nhan(): string
    {
        return self::NHAN[$this->hanh_dong] ?? $this->hanh_dong;
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(config('bao_mat.nhat_ky_giu_ngay', 180)));
    }
}
