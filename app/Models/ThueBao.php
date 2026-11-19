<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một lần chủ sân mua / gia hạn gói. */
class ThueBao extends Model
{
    protected $table = 'thue_bao';

    protected $guarded = ['*'];

    public const TRANG_THAI = ['cho_thanh_toan' => 'Chờ thanh toán', 'da_thanh_toan' => 'Đã thanh toán', 'da_huy' => 'Đã huỷ'];

    protected function casts(): array
    {
        return ['tu_ngay' => 'datetime', 'den_ngay' => 'datetime', 'xac_nhan_luc' => 'datetime'];
    }

    public function chuSan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chu_san_id');
    }

    public function goiThueBao(): BelongsTo
    {
        return $this->belongsTo(GoiThueBao::class);
    }

    public function noiDungChuyenKhoan(): string
    {
        return 'THUEBAO '.$this->id;
    }
}
