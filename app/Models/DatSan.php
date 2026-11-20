<?php

namespace App\Models;

use App\Support\ThoiGian;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DatSan extends Model
{
    protected $table = 'dat_san';

    public const TRANG_THAI = [
        'cho_thanh_toan' => 'Chờ cọc',
        'da_dat' => 'Đã đặt',
        'da_huy' => 'Đã huỷ',
    ];

    public const XU_LY_COC = [
        'khong_ap_dung' => '',
        'cho_hoan' => 'Chờ hoàn cọc',
        'da_hoan' => 'Đã hoàn cọc',
        'mat_coc' => 'Mất cọc',
    ];

    // Không có $fillable: mọi cột do DatSanService gán tường minh
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'giu_cho_den' => 'datetime',
            'bao_da_chuyen_luc' => 'datetime',
            'khong_tra_du_luc' => 'datetime',
            'da_nhac_bao_chuyen' => 'boolean',
            'xac_nhan_coc_luc' => 'datetime',
            'huy_luc' => 'datetime',
            'da_hoan_luc' => 'datetime',
            'da_nhac_lich_luc' => 'datetime',
            'gia_chot' => 'integer',
            'tien_coc' => 'integer',
        ];
    }

    /** URL dùng mã đơn (DS000123) thay vì id: /dat-san/DS000123/... */
    public function getRouteKeyName(): string
    {
        return 'ma_don';
    }

    public function san(): BelongsTo
    {
        return $this->belongsTo(San::class);
    }

    public function khach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'khach_id');
    }

    public function giaoDichCoc(): HasOne
    {
        return $this->hasOne(GiaoDich::class)->where('loai', 'coc_dat_san')->latestOfMany();
    }

    public function batDauLuc(): Carbon
    {
        return Carbon::parse($this->ngay)->startOfDay()->addMinutes(ThoiGian::phut($this->gio_bat_dau));
    }

    public function ketThucLuc(): Carbon
    {
        return Carbon::parse($this->ngay)->startOfDay()->addMinutes(ThoiGian::phut($this->gio_ket_thuc));
    }

    public function khungGioNhan(): string
    {
        return ThoiGian::chuoi(ThoiGian::phut($this->gio_bat_dau)).'–'.ThoiGian::chuoi(ThoiGian::phut($this->gio_ket_thuc));
    }

    public function tenNguoiDat(): string
    {
        return $this->khach?->name ?? (string) $this->ten_khach_tai_cho;
    }

    public function sdtNguoiDat(): string
    {
        return $this->khach?->so_dien_thoai ?? (string) $this->sdt_khach_tai_cho;
    }

    public function moTaNgan(): string
    {
        $thu = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][$this->batDauLuc()->dayOfWeek];

        return $this->san?->ten.' · '.$this->batDauLuc()->format('H:i').'–'.$this->ketThucLuc()->format('H:i').' · '.$thu.' '.$this->batDauLuc()->format('d/m/Y');
    }

    public function conLaiPhai(): int
    {
        return max(0, $this->gia_chot - ($this->xac_nhan_coc_luc ? $this->tien_coc : 0));
    }

    public function dangGiuCho(): bool
    {
        return $this->trang_thai === 'cho_thanh_toan' && $this->giu_cho_den?->isFuture();
    }
}
