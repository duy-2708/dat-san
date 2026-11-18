<?php

namespace App\Models;

use App\Support\ChuanHoa;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Một bảng users cho mọi vai trò. Cột vai_tro quyết định quyền;
 * nhân viên có chu_san_id trỏ về chủ sân của mình.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    public const ADMIN = 'admin';
    public const CHU_SAN = 'chu_san';
    public const NHAN_VIEN = 'nhan_vien';
    public const KHACH = 'khach';

    public const TEN_VAI_TRO = [
        self::ADMIN => 'Quản trị',
        self::CHU_SAN => 'Chủ sân',
        self::NHAN_VIEN => 'Nhân viên',
        self::KHACH => 'Khách',
    ];

    /**
     * Chỉ các cột người dùng được phép tự điền. vai_tro, chu_san_id, trang_thai
     * KHÔNG nằm đây → không ai gửi form kèm "vai_tro=admin" để tự nâng quyền được.
     */
    protected $fillable = [
        'name', 'email', 'so_dien_thoai', 'password',
        'anh_dai_dien', 'vi_tri_so_truong',
    ];

    protected $hidden = ['password', 'remember_token', 'google_id'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'sdt_xac_thuc_luc' => 'datetime',
            'khoa_keo_den' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // Luôn cập nhật email_chuan_hoa theo email → UNIQUE trong CSDL chặn email "biến thể"
        static::saving(function (User $user) {
            $user->email = mb_strtolower(trim($user->email));
            $user->email_chuan_hoa = ChuanHoa::email($user->email);
        });
    }

    // ---- Quan hệ ----

    public function chuSan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chu_san_id');
    }

    public function nhanViens(): HasMany
    {
        return $this->hasMany(User::class, 'chu_san_id');
    }

    public function hoSoChuSan(): HasOne
    {
        return $this->hasOne(HoSoChuSan::class);
    }

    // ---- Vai trò ----

    public function laAdmin(): bool
    {
        return $this->vai_tro === self::ADMIN;
    }

    public function laChuSan(): bool
    {
        return $this->vai_tro === self::CHU_SAN;
    }

    public function laNhanVien(): bool
    {
        return $this->vai_tro === self::NHAN_VIEN;
    }

    public function laKhach(): bool
    {
        return $this->vai_tro === self::KHACH;
    }

    public function coVaiTro(string ...$vaiTro): bool
    {
        return in_array($this->vai_tro, $vaiTro, true);
    }

    /**
     * ID chủ sân mà tài khoản này làm việc cho: chủ sân → chính mình, nhân viên → chủ của mình.
     * Dùng để lọc dữ liệu "của mình" ở mọi màn hình quản lý.
     */
    public function idChuSanQuanLy(): ?int
    {
        return match ($this->vai_tro) {
            self::CHU_SAN => $this->id,
            self::NHAN_VIEN => $this->chu_san_id,
            default => null,
        };
    }

    /** Bị khoá trực tiếp, hoặc là nhân viên của chủ sân đang bị khoá. */
    public function biKhoa(): bool
    {
        if ($this->trang_thai === 'bi_khoa') {
            return true;
        }

        return $this->laNhanVien() && $this->chuSan?->trang_thai === 'bi_khoa';
    }

    public function tenVaiTro(): string
    {
        return self::TEN_VAI_TRO[$this->vai_tro] ?? $this->vai_tro;
    }
}
