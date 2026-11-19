<?php

namespace App\Models;

use App\Support\ThoiGian;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KhungGio extends Model
{
    protected $table = 'khung_gio';

    public const LOAI_NGAY = [
        'ngay_thuong' => 'Ngày thường (T2–T6)',
        'cuoi_tuan' => 'Cuối tuần (T7, CN)',
        'ngay_le' => 'Ngày lễ',
    ];

    protected $fillable = ['loai_ngay', 'gio_bat_dau', 'gio_ket_thuc', 'gia', 'tien_coc', 'la_gio_vang', 'dang_ap_dung'];

    protected function casts(): array
    {
        return [
            'la_gio_vang' => 'boolean',
            'dang_ap_dung' => 'boolean',
            'gia' => 'integer',
            'tien_coc' => 'integer',
        ];
    }

    public function san(): BelongsTo
    {
        return $this->belongsTo(San::class);
    }

    public function phutBatDau(): int
    {
        return ThoiGian::phut($this->gio_bat_dau);
    }

    public function phutKetThuc(): int
    {
        return ThoiGian::phut($this->gio_ket_thuc);
    }

    public function nhan(): string
    {
        return ThoiGian::chuoi($this->phutBatDau()).'–'.ThoiGian::chuoi($this->phutKetThuc());
    }

    /** Tiền cọc: chủ sân đặt riêng cho khung này, nếu không thì theo tỉ lệ (mặc định 30%) làm tròn LÊN nghìn đồng. */
    public function tienCoc(int $tyLe = 30): int
    {
        if ($this->tien_coc !== null) {
            return min((int) $this->tien_coc, (int) $this->gia);
        }

        return (int) (ceil($this->gia * $tyLe / 100 / 1000) * 1000);
    }
}
