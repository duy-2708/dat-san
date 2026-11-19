<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoiThueBao extends Model
{
    protected $table = 'goi_thue_bao';

    protected $fillable = [
        'ten', 'gia_thang', 'so_san_toi_da', 'so_nhan_vien_toi_da',
        'cho_phep_cong_tu_dong', 'mo_ta', 'dang_ban',
    ];

    protected function casts(): array
    {
        return [
            'cho_phep_cong_tu_dong' => 'boolean',
            'dang_ban' => 'boolean',
        ];
    }

    public function hoSoChuSans(): HasMany
    {
        return $this->hasMany(HoSoChuSan::class);
    }
}
