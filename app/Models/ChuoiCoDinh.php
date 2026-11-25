<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChuoiCoDinh extends Model
{
    protected $table = 'chuoi_co_dinh';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['den_ngay' => 'date'];
    }

    public function san(): BelongsTo
    {
        return $this->belongsTo(San::class);
    }

    public function dat(): HasMany
    {
        return $this->hasMany(DatSan::class, 'chuoi_co_dinh_id');
    }
}
