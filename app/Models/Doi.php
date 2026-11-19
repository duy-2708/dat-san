<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Doi extends Model
{
    protected $table = 'doi';

    protected $guarded = ['*'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function doiTruong(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doi_truong_id');
    }

    public function tinhThanh(): BelongsTo
    {
        return $this->belongsTo(TinhThanh::class);
    }

    public function thanhViens(): HasMany
    {
        return $this->hasMany(ThanhVienDoi::class);
    }

    public function dangChoi(): HasMany
    {
        return $this->thanhViens()->where('trang_thai', 'dang_choi');
    }

    public function thamGiaGiais(): HasMany
    {
        return $this->hasMany(DoiGiaiDau::class);
    }

    public function logoUrl(): string
    {
        return $this->logo
            ? Storage::disk('public')->url($this->logo)
            : 'https://ui-avatars.com/api/?background=0d6efd&color=fff&bold=true&name='.urlencode($this->ten);
    }

    public function laDoiTruong(?User $u): bool
    {
        return $u !== null && (int) $this->doi_truong_id === $u->id;
    }

    public function laThanhVien(?User $u): bool
    {
        return $u !== null && $this->dangChoi()->where('user_id', $u->id)->exists();
    }
}
