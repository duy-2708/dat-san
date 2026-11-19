<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một lần nhận tiền (cọc) — tiền đi THẲNG vào tài khoản chủ sân, website chỉ ghi nhận. */
class GiaoDich extends Model
{
    protected $table = 'giao_dich';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'thanh_toan_luc' => 'datetime',
            'du_lieu_cong' => 'array',
            'so_tien' => 'integer',
        ];
    }

    public function nguoiTra(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nguoi_tra_id');
    }

    public function datSan(): BelongsTo
    {
        return $this->belongsTo(DatSan::class);
    }
}
