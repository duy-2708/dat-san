<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DoiGiaiDau extends Model
{
    protected $table = 'doi_giai_dau';

    protected $guarded = ['*'];

    public function doi(): BelongsTo
    {
        return $this->belongsTo(Doi::class);
    }

    public function giaiDau(): BelongsTo
    {
        return $this->belongsTo(GiaiDau::class);
    }

    /** Danh sách cầu thủ CHỤP lúc đăng ký giải (rời đội sau này vẫn giữ thành tích). */
    public function cauThus(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cau_thu_giai_dau', 'doi_giai_dau_id', 'user_id');
    }
}
