<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keo extends Model
{
    protected $table = 'keo';

    protected $guarded = ['*'];

    public const TY_LE = [50 => '5/5', 60 => '6/4', 70 => '7/3', 100 => 'Thua chịu hết'];
    public const TRINH_DO = ['moi_choi' => 'Mới chơi', 'trung_binh' => 'Trung bình', 'kha' => 'Khá', 'gioi' => 'Giỏi'];
    public const KET_QUA = ['a_thang' => 'Bên A thắng', 'b_thang' => 'Bên B thắng', 'hoa' => 'Hoà'];
    public const TRANG_THAI = [
        'dang_tim' => 'Đang tìm', 'da_chot' => 'Đã chốt', 'cho_xac_nhan' => 'Chờ xác nhận kết quả',
        'tranh_chap' => 'Tranh chấp', 'hoan_tat' => 'Hoàn tất', 'da_huy' => 'Đã huỷ',
    ];

    protected function casts(): array
    {
        return [
            'han_xac_nhan_luc' => 'datetime', 'a_bao_luc' => 'datetime', 'b_bao_luc' => 'datetime',
            'hoan_tat_luc' => 'datetime', 'diem_danh_luc' => 'datetime',
            'tien_san' => 'integer', 'coc_moi_ben' => 'integer', 'tien_moi_nguoi' => 'integer',
            'tien_coc_ca_nhan' => 'integer', 'ty_le_thua' => 'integer', 'so_nguoi_can' => 'integer',
        ];
    }

    public function datSan(): BelongsTo
    {
        return $this->belongsTo(DatSan::class);
    }

    public function nguoiTao(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nguoi_tao_id');
    }

    public function daiDienB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dai_dien_b_id');
    }

    public function thamGias(): HasMany
    {
        return $this->hasMany(ThamGiaKeo::class);
    }

    public function laDoiDau(): bool
    {
        return $this->loai === 'doi_dau';
    }

    /** Người đang có mặt trong kèo (không tính đã rời/bị huỷ). */
    public function thanhVien(?string $ben = null)
    {
        return $this->thamGias()->whereIn('trang_thai', ['cho_coc', 'da_xac_nhan'])->when($ben, fn ($q) => $q->where('ben', $ben));
    }

    public function benCua(User $u): ?string
    {
        return $this->thamGias()->where('user_id', $u->id)->whereIn('trang_thai', ['cho_coc', 'da_xac_nhan'])->value('ben');
    }

    public function laDaiDien(User $u, ?string $ben = null): bool
    {
        $a = (int) $this->nguoi_tao_id === $u->id;
        $b = $this->dai_dien_b_id && (int) $this->dai_dien_b_id === $u->id;

        return match ($ben) { 'a' => $a, 'b' => $b, default => $a || $b };
    }
}
