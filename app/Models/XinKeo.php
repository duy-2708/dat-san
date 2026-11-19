<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một đội/người xin nhận kèo (kèo trước), chờ chủ kèo hoặc sân duyệt. */
class XinKeo extends Model
{
    protected $table = 'xin_keo';

    protected $guarded = ['*'];

    public function keo(): BelongsTo
    {
        return $this->belongsTo(Keo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function doi(): BelongsTo
    {
        return $this->belongsTo(Doi::class);
    }
}
