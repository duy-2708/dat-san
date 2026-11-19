<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThamGiaKeo extends Model
{
    protected $table = 'tham_gia_keo';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['giu_cho_den' => 'datetime', 'mien_phat' => 'boolean'];
    }

    public function keo(): BelongsTo
    {
        return $this->belongsTo(Keo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
