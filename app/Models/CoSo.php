<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CoSo extends Model
{
    protected $table = 'co_so';

    protected $guarded = ['*'];

    public const COT_CHUNG = ['xa_phuong_id', 'dia_chi_chi_tiet', 'mo_ta', 'tien_ich', 'gio_mo_cua', 'gio_dong_cua'];

    protected function casts(): array
    {
        return ['tien_ich' => 'array'];
    }

    public static function taoTuSan(San $s, ?string $ten = null): self
    {
        $c = new self;
        $c->forceFill(collect(self::COT_CHUNG)->mapWithKeys(fn ($cot) => [$cot => $s->getAttribute($cot)])->all() + [
            'chu_san_id' => $s->chu_san_id,
            'ten' => $ten ?: $s->ten,
            'slug' => Str::slug($ten ?: $s->ten).'-'.Str::lower(Str::random(6)),
        ])->save();

        return $c;
    }

    public function chuSan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chu_san_id');
    }

    public function xaPhuong(): BelongsTo
    {
        return $this->belongsTo(XaPhuong::class);
    }

    public function sans(): HasMany
    {
        return $this->hasMany(San::class)->orderBy('loai_san')->orderBy('ten');
    }

    public function giaTriChung(): array
    {
        return collect(self::COT_CHUNG)->mapWithKeys(fn ($cot) => [$cot => $this->getAttribute($cot)])->all();
    }

    public function dongBoXuongSan(): void
    {
        foreach ($this->sans()->get() as $s) {
            $s->forceFill($this->giaTriChung())->saveQuietly();
        }
    }

    public function diaChiDayDu(): string
    {
        $xp = $this->xaPhuong;

        return trim($this->dia_chi_chi_tiet.', '.$xp?->ten.', '.$xp?->tinhThanh?->ten, ', ');
    }
}
