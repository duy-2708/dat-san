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
        'dang_tim' => 'Đang tìm', 'cho_coc' => 'Chờ hai đội cọc', 'da_chot' => 'Đã chốt', 'cho_xac_nhan' => 'Chờ xác nhận kết quả',
        'tranh_chap' => 'Tranh chấp', 'hoan_tat' => 'Hoàn tất', 'da_huy' => 'Đã huỷ',
    ];

    protected function casts(): array
    {
        return [
            'han_xac_nhan_luc' => 'datetime', 'a_bao_luc' => 'datetime', 'b_bao_luc' => 'datetime',
            'hoan_tat_luc' => 'datetime', 'diem_danh_luc' => 'datetime', 'han_coc_luc' => 'datetime', 'ngay' => 'date',
            'cong_khai' => 'boolean', 'mien_coc' => 'boolean', 'thoi_luong' => 'integer',
            'tien_san' => 'integer', 'coc_moi_ben' => 'integer', 'tien_moi_nguoi' => 'integer',
            'tien_coc_ca_nhan' => 'integer', 'ty_le_thua' => 'integer', 'so_nguoi_can' => 'integer',
        ];
    }

    public function datSan(): BelongsTo
    {
        return $this->belongsTo(DatSan::class);
    }

    public function san(): BelongsTo
    {
        return $this->belongsTo(San::class);
    }

    public function khungGio(): BelongsTo
    {
        return $this->belongsTo(KhungGio::class);
    }

    public function doiA(): BelongsTo
    {
        return $this->belongsTo(Doi::class, 'doi_a_id');
    }

    public function doiB(): BelongsTo
    {
        return $this->belongsTo(Doi::class, 'doi_b_id');
    }

    public function daiDienA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dai_dien_a_id');
    }

    public function xinKeos(): HasMany
    {
        return $this->hasMany(XinKeo::class);
    }

    /** Kèo tạo trước khi đặt sân (chốt xong mới đặt). */
    public function laKeoTruoc(): bool
    {
        return $this->san_id !== null;
    }

    /** Sân của kèo (kèo trước: chọn lúc tạo; kèo thường: sân của đơn đặt). */
    public function sanThuc(): San
    {
        return $this->datSan?->san ?? $this->san;
    }

    public function batDauLuc(): \Carbon\Carbon
    {
        if ($this->datSan) {
            return $this->datSan->batDauLuc();
        }

        return $this->ngay->copy()->startOfDay()->addMinutes($this->khungGio->phutBatDau());
    }

    public function ketThucLuc(): \Carbon\Carbon
    {
        return $this->datSan ? $this->datSan->ketThucLuc() : $this->batDauLuc()->addMinutes($this->thoi_luong);
    }

    public function nhanGio(): string
    {
        return $this->batDauLuc()->format('H:i').'–'.$this->ketThucLuc()->format('H:i');
    }

    /** Người được coi là đại diện bên A (kèo sân tạo hộ: chủ sân/nhân viên tạo kèo đứng thay). */
    public function idDaiDienA(): ?int
    {
        return $this->dai_dien_a_id ? (int) $this->dai_dien_a_id : (int) $this->nguoi_tao_id;
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
        $a = $this->idDaiDienA() === $u->id;
        $b = $this->dai_dien_b_id && (int) $this->dai_dien_b_id === $u->id;

        return match ($ben) { 'a' => $a, 'b' => $b, default => $a || $b };
    }
}
