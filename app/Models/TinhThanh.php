<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TinhThanh extends Model
{
    use HasFactory;

    protected $table = 'tinh_thanh';

    public $timestamps = false;

    protected $fillable = ['ma', 'ten', 'loai', 'thu_tu'];

    public function xaPhuongs(): HasMany
    {
        return $this->hasMany(XaPhuong::class);
    }
}
