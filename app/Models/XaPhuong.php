<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XaPhuong extends Model
{
    protected $table = 'xa_phuong';

    public $timestamps = false;

    protected $fillable = ['tinh_thanh_id', 'ma', 'ten', 'loai'];

    public function tinhThanh(): BelongsTo
    {
        return $this->belongsTo(TinhThanh::class);
    }
}
