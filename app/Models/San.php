<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class San extends Model
{
    use HasFactory;

    protected $table = 'san';

    public const LOAI = [5 => 'Sân 5', 7 => 'Sân 7', 11 => 'Sân 11'];

    public const TIEN_ICH = [
        'den_chieu_sang' => 'Đèn chiếu sáng',
        'gui_xe' => 'Giữ xe',
        'wifi' => 'Wifi',
        'nuoc_uong' => 'Nước uống',
        'phong_thay_do' => 'Phòng thay đồ',
        'wc' => 'Nhà vệ sinh',
        'cho_thue_giay' => 'Cho thuê giày',
        'cho_thue_ao' => 'Cho thuê áo bib',
        'cang_tin' => 'Căng tin',
        'mai_che' => 'Mái che khán đài',
    ];

    protected $fillable = [
        'xa_phuong_id', 'dia_chi_chi_tiet', 'ten', 'biet_danh', 'loai_san', 'mo_ta',
        'tien_ich', 'gio_mo_cua', 'gio_dong_cua', 'vi_do', 'kinh_do',
    ];

    protected function casts(): array
    {
        return [
            'tien_ich' => 'array',
            'loai_san' => 'integer',
            'gia_rieng' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (San $s) {
            if (! $s->co_so_id) {
                $s->forceFill(['co_so_id' => CoSo::taoTuSan($s)->id])->saveQuietly();
            }
        });
    }

    public function coSo(): BelongsTo
    {
        return $this->belongsTo(CoSo::class);
    }

    public function cungLoaiCungCoSo(): \Illuminate\Support\Collection
    {
        return San::where('co_so_id', $this->co_so_id)->where('loai_san', $this->loai_san)->where('trang_thai', 'hoat_dong')
            ->orderBy('ten')->get()->sortBy(fn ($x) => $x->id === $this->id ? 0 : 1)->values();
    }

    // ---- Quan hệ ----

    public function chuSan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chu_san_id');
    }

    public function xaPhuong(): BelongsTo
    {
        return $this->belongsTo(XaPhuong::class);
    }

    public function anhSans(): HasMany
    {
        return $this->hasMany(AnhSan::class)->orderBy('thu_tu')->orderBy('id');
    }

    public function khungGios(): HasMany
    {
        return $this->hasMany(KhungGio::class)->orderBy('gio_bat_dau');
    }

    /** Các sân nhỏ ghép thành sân này (VD sân 11 gồm 5A, 5B...). */
    public function sanCons(): BelongsToMany
    {
        return $this->belongsToMany(San::class, 'san_ghep', 'san_cha_id', 'san_con_id');
    }

    /** Các sân lớn mà sân này là một phần. */
    public function sanChas(): BelongsToMany
    {
        return $this->belongsToMany(San::class, 'san_ghep', 'san_con_id', 'san_cha_id');
    }

    // ---- Tiện ích ----

    public function tenHienThi(): string
    {
        return $this->biet_danh ? "{$this->ten} ({$this->biet_danh})" : $this->ten;
    }

    public function diaChiDayDu(): string
    {
        $xp = $this->xaPhuong;

        return trim($this->dia_chi_chi_tiet.', '.$xp?->ten.', '.$xp?->tinhThanh?->ten, ', ');
    }

    public function anhDaiDien(): string
    {
        $anh = $this->relationLoaded('anhSans') ? $this->anhSans->first() : $this->anhSans()->first();

        return $anh ? $anh->url() : asset('images/san-mac-dinh.svg');
    }

    /**
     * ID mọi sân "dính" với sân này qua ghép (tổ tiên + con cháu, bắc cầu).
     * Giai đoạn 3 dùng để khoá ô lịch: đặt sân này thì khoá luôn các sân trong danh sách.
     *
     * @return int[]
     */
    public function idsSanLienQuan(): array
    {
        return array_values(array_unique(array_merge(
            self::duyetGhep([$this->id], 'san_cha_id', 'san_con_id'), // con cháu
            self::duyetGhep([$this->id], 'san_con_id', 'san_cha_id'), // tổ tiên
        )));
    }

    /**
     * ID các sân con, cháu (bắc cầu). Đặt sân này → chiếm ô của nó + toàn bộ sân con.
     * Không chiếm ô sân cha: nếu không, hai sân con (5A, 5B) đặt cùng giờ sẽ "đụng" nhau ở sân cha.
     * Sân cha vẫn bị chặn vì khi đặt sân cha sẽ đụng ô của sân con đã có người đặt.
     *
     * @return int[]
     */
    public function idsSanCon(): array
    {
        return self::duyetGhep([$this->id], 'san_cha_id', 'san_con_id');
    }

    /** Duyệt bảng san_ghep theo một chiều (BFS), không gồm các id xuất phát. */
    public static function duyetGhep(array $batDau, string $cotTu, string $cotDen): array
    {
        $daThay = [];
        $hangDoi = $batDau;
        while ($hangDoi) {
            $moi = DB::table('san_ghep')->whereIn($cotTu, $hangDoi)->pluck($cotDen)->all();
            $moi = array_diff($moi, $daThay, $batDau);
            $daThay = array_merge($daThay, $moi);
            $hangDoi = $moi;
        }

        return array_map('intval', $daThay);
    }
}
