<?php

namespace App\Services;

use App\Models\Keo;
use App\Models\KhungGio;
use App\Models\San;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BangGiaChung
{
    private const COT = ['loai_ngay', 'gio_bat_dau', 'gio_ket_thuc', 'gia', 'tien_coc', 'la_gio_vang', 'dang_ap_dung'];

    public function sanNhan(San $nguon): Collection
    {
        if ($nguon->gia_rieng || ! $nguon->co_so_id) {
            return collect();
        }

        return San::where('co_so_id', $nguon->co_so_id)->where('loai_san', $nguon->loai_san)
            ->where('id', '!=', $nguon->id)->where('gia_rieng', false)->orderBy('ten')->get();
    }

    public function dongBo(San $nguon): int
    {
        $dich = $this->sanNhan($nguon);
        if ($dich->isEmpty()) {
            return 0;
        }
        $mau = KhungGio::where('san_id', $nguon->id)->get();
        foreach ($dich as $s) {
            DB::transaction(fn () => $this->chep($mau, $s));
        }

        return $dich->count();
    }

    public function nhanTuSanCungLoai(San $moi): bool
    {
        if ($moi->gia_rieng) {
            return false;
        }
        $mau = San::where('co_so_id', $moi->co_so_id)->where('loai_san', $moi->loai_san)->where('id', '!=', $moi->id)
            ->where('gia_rieng', false)->whereHas('khungGios')->orderBy('id')->first();
        if (! $mau) {
            return false;
        }
        DB::transaction(fn () => $this->chep(KhungGio::where('san_id', $mau->id)->get(), $moi));

        return true;
    }

    private function chep(Collection $mau, San $s): void
    {
        $hienCo = KhungGio::where('san_id', $s->id)->get()->keyBy(fn ($k) => $k->loai_ngay.'|'.$k->gio_bat_dau);
        $giu = [];
        foreach ($mau as $m) {
            $k = $hienCo->get($m->loai_ngay.'|'.$m->gio_bat_dau) ?? new KhungGio;
            $k->forceFill($m->only(self::COT) + ['san_id' => $s->id])->save();
            $giu[] = $k->id;
        }
        foreach ($hienCo->whereNotIn('id', $giu) as $k) {
            $dangDung = Keo::where('khung_gio_id', $k->id)->whereIn('trang_thai', ['dang_tim', 'cho_coc'])->exists();
            $dangDung ? $k->forceFill(['dang_ap_dung' => false])->save() : $k->delete();
        }
    }
}
