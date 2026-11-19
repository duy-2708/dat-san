<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThanhVienDoi extends Model
{
    protected $table = 'thanh_vien_doi';

    protected $guarded = ['*'];

    public function doi(): BelongsTo
    {
        return $this->belongsTo(Doi::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
