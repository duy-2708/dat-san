<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoSoChuSan extends Model
{
    protected $table = 'ho_so_chu_san';

    protected $fillable = [
        'ten_co_so', 'sdt_lien_he',
        'phuong_thuc_tt', 'ngan_hang_bin', 'so_tai_khoan', 'ten_chu_tai_khoan',
        'payos_client_id', 'payos_api_key', 'payos_checksum_key',
        'phut_giu_cho', 'gio_huy_hoan_coc',
    ];

    protected $hidden = ['payos_client_id', 'payos_api_key', 'payos_checksum_key', 'webhook_token'];

    protected function casts(): array
    {
        return [
            'het_han_luc' => 'datetime',
            'bi_khoa_do_het_han' => 'boolean',
            // Laravel tự mã hoá khi lưu, giải mã khi đọc, bằng APP_KEY. Lộ CSDL cũng không lộ khoá API.
            'payos_client_id' => 'encrypted',
            'payos_api_key' => 'encrypted',
            'payos_checksum_key' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function goiThueBao(): BelongsTo
    {
        return $this->belongsTo(GoiThueBao::class);
    }

    public function conHan(): bool
    {
        return $this->het_han_luc !== null && $this->het_han_luc->isFuture();
    }
}
