<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YeuCauGoPhat extends Model
{
    protected $table = 'yeu_cau_go_phat';

    protected $guarded = ['*'];

    public const LOAI = ['mien_phat' => 'Miễn phạt vắng (+uy tín)', 'go_han_che' => 'Gỡ hạn chế / cấm đặt'];

    protected function casts(): array
    {
        return ['xu_ly_luc' => 'datetime'];
    }

    public function chuSan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chu_san_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function thamGia(): BelongsTo
    {
        return $this->belongsTo(ThamGiaKeo::class, 'tham_gia_keo_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
