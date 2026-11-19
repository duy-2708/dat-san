<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Once;

/**
 * Cài đặt hệ thống dạng khoá–giá trị.
 *  - mien_phi_den: ngày (Y-m-d) cuối của thời gian miễn phí. Trong thời gian này mọi chủ sân
 *    được dùng đầy đủ, KHÔNG giới hạn số sân/nhân viên, không bị khoá vì hết hạn.
 *  - tk_bin, tk_so, tk_ten: tài khoản nhận tiền thuê bao của admin (VietQR).
 */
class CaiDat extends Model
{
    protected $table = 'cai_dat';

    protected $primaryKey = 'khoa';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public static function lay(string $khoa, ?string $macDinh = null): ?string
    {
        $tatCa = once(fn () => static::query()->pluck('gia_tri', 'khoa')->all());

        return $tatCa[$khoa] ?? $macDinh;
    }

    public static function dat(array $giaTri): void
    {
        foreach ($giaTri as $k => $v) {
            static::query()->updateOrCreate(['khoa' => $k], ['gia_tri' => $v === '' ? null : $v]);
        }
        Once::flush();
    }

    public static function mienPhiDen(): ?Carbon
    {
        $d = static::lay('mien_phi_den');

        return $d ? Carbon::parse($d)->endOfDay() : null;
    }

    public static function dangMienPhi(): bool
    {
        return (bool) static::mienPhiDen()?->isFuture();
    }
}
