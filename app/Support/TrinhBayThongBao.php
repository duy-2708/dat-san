<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class TrinhBayThongBao
{
    private const XU_LY = '/báo đã chuyển|tranh chấp|gỡ phạt mới|xin nhận kèo|xin vào đội|đăng ký giải|mua gói|đối thủ đã báo|bị lệch|có đội xin|kèo đã chốt|đăng nhập sai/ui';

    public static function mot(DatabaseNotification $n): array
    {
        $d = $n->data;
        $tieuDe = (string) ($d['tieu_de'] ?? '');
        $noiDung = (string) ($d['noi_dung'] ?? '');
        $url = (string) ($d['url'] ?? '');
        $icon = (string) ($d['icon'] ?? 'bell');
        preg_match('/\b(?:DS|DK)\d{4,}\b/', $tieuDe.' '.$noiDung, $ma);
        preg_match('/\d{1,3}(?:\.\d{3})+đ/u', $noiDung, $tien);

        $ngan = trim(preg_replace('/\s*\b(?:DS|DK)\d{4,}\b/', '', $tieuDe));
        if ($tien && ! str_contains($ngan, $tien[0])) {
            $ngan .= ' · '.$tien[0];
        }
        $dong2 = preg_split('/(?<=[^\d])\.\s/u', $noiDung)[0] ?? $noiDung;
        $dong2 = preg_replace(['/–\d{2}:\d{2}/', '/(\d{2}\/\d{2})\/\d{4}/', '/\s*\b(?:DS|DK)\d{4,}\b/', '/ báo đã chuyển \S+ cho/u', '/, chờ cọc \S+/u', '/ giữ /u'], ['', '$1', '', ' ·', '', ' · '], $dong2);

        $mau = match (true) {
            in_array($icon, ['check-circle', 'calendar-plus', 'bag-check', 'trophy', 'arrow-up-circle', 'arrow-counterclockwise'], true) => 'success',
            in_array($icon, ['x-circle', 'exclamation-triangle', 'exclamation-diamond', 'arrow-down-circle', 'shield-exclamation'], true) => 'danger',
            in_array($icon, ['cash-coin', 'hourglass-bottom', 'hourglass-split', 'flag'], true) => 'warning',
            default => 'primary',
        };
        $nhom = str_contains($url, '/keo') || str_contains(mb_strtolower($tieuDe), 'kèo') ? 'keo'
            : ($ma || str_contains($url, 'dat-san') || str_contains(mb_strtolower($tieuDe), 'đơn') ? 'don' : 'khac');

        return [
            'id' => $n->id, 'tieu_de' => $ngan ?: $tieuDe, 'dong2' => trim($dong2, " .·"), 'day_du' => $tieuDe."\n".$noiDung,
            'icon' => $icon, 'mau' => $mau, 'nhom' => $nhom, 'can_xu_ly' => (bool) preg_match(self::XU_LY, $tieuDe),
            'khoa' => $ma[0] ?? ($url ?: $n->id), 'chua_doc' => $n->read_at === null, 'luc' => $n->created_at,
        ];
    }

    public static function gop(Collection $ds): Collection
    {
        return $ds->map(fn ($n) => self::mot($n))->groupBy('khoa')
            ->map(fn ($g) => ['so_cap_nhat' => $g->count(), 'chua_doc' => $g->contains('chua_doc', true), 'can_xu_ly' => $g->contains('can_xu_ly', true)]
                + $g->sortByDesc(fn ($t) => [$t['luc']->getTimestamp(), (int) $t['can_xu_ly']])->first())
            ->values();
    }
}
