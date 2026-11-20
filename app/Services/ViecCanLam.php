<?php

namespace App\Services;

use App\Models\DatSan;
use App\Models\GiaoDich;
use App\Models\Keo;
use App\Models\User;
use Illuminate\Support\Collection;

class ViecCanLam
{
    private array $daTinh = [];

    public function cua(?User $u): Collection
    {
        if (! $u || ! $u->laKhach()) {
            return collect();
        }

        return $this->daTinh[$u->id] ??= $this->tinh($u);
    }

    private function tinh(User $u): Collection
    {
        $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ';
        $ds = collect();

        DatSan::with('san')->where('khach_id', $u->id)->where('trang_thai', 'cho_thanh_toan')
            ->where('ngay', '>=', today()->toDateString())->orderBy('ngay')->get()
            ->each(fn ($d) => $ds->push([
                'loai' => 'coc_san', 'nhan' => 'Cọc đặt sân',
                'mo_ta' => $d->san->ten.' · '.$d->batDauLuc()->format('H:i d/m/Y').' · mã '.$d->ma_don,
                'so_tien' => $tien($d->tien_coc), 'url' => route('dat-san.thanh-toan', $d), 'nut' => 'Thanh toán cọc',
            ]));

        GiaoDich::where('nguoi_tra_id', $u->id)->where('trang_thai', 'cho_thanh_toan')->whereNull('dat_san_id')->latest('id')->get()
            ->each(fn ($g) => $ds->push([
                'loai' => 'coc_keo', 'nhan' => 'Cọc kèo',
                'mo_ta' => 'Nội dung chuyển khoản '.$g->noi_dung_ck,
                'so_tien' => $tien($g->so_tien), 'url' => route('keo.coc', $g), 'nut' => 'Thanh toán cọc',
            ]));

        DatSan::with('san')->where('khach_id', $u->id)->where('trang_thai', 'da_dat')->whereDate('ngay', today())->get()
            ->filter(fn ($d) => $d->conLaiPhai() > 0 && $d->ketThucLuc()->isFuture())
            ->each(fn ($d) => $ds->push([
                'loai' => 'tra_hom_nay', 'nhan' => 'Trả tại sân hôm nay',
                'mo_ta' => '⚽ '.$d->moTaNgan().' · mang theo',
                'so_tien' => $tien($d->conLaiPhai()), 'url' => route('dat-san.thanh-toan', $d), 'nut' => 'Xem đơn',
            ]));

        Keo::with(['doiA', 'doiB', 'datSan.san'])->where('loai', 'doi_dau')
            ->whereIn('trang_thai', ['da_chot', 'cho_xac_nhan', 'tranh_chap'])->whereNotNull('dat_san_id')
            ->where(fn ($q) => $q->where('dai_dien_a_id', $u->id)->orWhere('nguoi_tao_id', $u->id)->orWhere('dai_dien_b_id', $u->id))
            ->get()
            ->filter(fn (Keo $k) => $k->datSan && $k->datSan->batDauLuc()->lte(now()))
            ->each(function (Keo $k) use ($u, $ds) {
                $ben = $k->laDaiDien($u, 'a') ? 'a' : ($k->laDaiDien($u, 'b') ? 'b' : null);
                if (! $ben || ($k->{$ben.'_bao_ket_qua'} && $k->trang_thai !== 'tranh_chap')) {
                    return;
                }
                $ds->push([
                    'loai' => 'ket_qua', 'nhan' => $k->trang_thai === 'tranh_chap' ? 'Kết quả đang lệch — xác nhận lại' : 'Xác nhận đội thắng',
                    'mo_ta' => $k->tenBen('a').' ⚔ '.$k->tenBen('b').' · '.$k->datSan->san->ten.' · '.$k->batDauLuc()->format('H:i d/m/Y'),
                    'so_tien' => null, 'url' => route('keo.show', $k), 'nut' => 'Xem kèo', 'keo' => $k,
                ]);
            });

        return $ds;
    }
}
