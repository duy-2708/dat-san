<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranDau extends Model
{
    protected $table = 'tran_dau';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['thoi_gian' => 'datetime', 'ban_thang_nha' => 'integer', 'ban_thang_khach' => 'integer'];
    }

    public function giaiDau(): BelongsTo
    {
        return $this->belongsTo(GiaiDau::class);
    }

    public function doiNha(): BelongsTo
    {
        return $this->belongsTo(Doi::class, 'doi_nha_id');
    }

    public function doiKhach(): BelongsTo
    {
        return $this->belongsTo(Doi::class, 'doi_khach_id');
    }

    public function san(): BelongsTo
    {
        return $this->belongsTo(San::class);
    }
}
