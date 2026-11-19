<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiaiDau extends Model
{
    protected $table = 'giai_dau';

    protected $guarded = ['*'];

    public const THE_THUC = ['vong_tron' => 'Vòng tròn tính điểm', 'loai_truc_tiep' => 'Loại trực tiếp', 'chia_bang' => 'Chia bảng'];
    public const TRANG_THAI = ['mo_dang_ky' => 'Đang nhận đăng ký', 'dang_dien_ra' => 'Đang diễn ra', 'ket_thuc' => 'Đã kết thúc', 'da_huy' => 'Đã huỷ'];

    protected function casts(): array
    {
        return ['le_phi' => 'integer', 'so_doi_toi_da' => 'integer', 'loai_san' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function chuSan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chu_san_id');
    }

    public function doiThamGias(): HasMany
    {
        return $this->hasMany(DoiGiaiDau::class);
    }

    public function doiDaDuyet(): HasMany
    {
        return $this->doiThamGias()->where('trang_thai', 'da_duyet');
    }

    public function tranDaus(): HasMany
    {
        return $this->hasMany(TranDau::class)->orderBy('id');
    }
}
