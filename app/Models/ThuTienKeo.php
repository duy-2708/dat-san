<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThuTienKeo extends Model
{
    protected $table = 'thu_tien_keo';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['so_tien' => 'integer', 'thu_luc' => 'datetime'];
    }

    public function keo(): BelongsTo
    {
        return $this->belongsTo(Keo::class);
    }

    public function nguoi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nguoi_id');
    }
}
