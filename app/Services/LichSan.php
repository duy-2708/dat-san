<?php

namespace App\Services;

use App\Models\DatSan;
use App\Models\San;
use App\Support\ThoiGian;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Dựng lịch 7 ngày của một sân: mỗi ngày gồm các khung giờ và trạng thái
 *  - trong:   còn trống, đặt được
 *  - dang_giu: có người đang giữ chỗ chờ cọc
 *  - da_dat:  đã đặt (hoặc bị khoá vì sân ghép liên quan đã được đặt)
 *  - qua_gio: đã qua, không đặt được
 * Khi $chiTiet = false (khách / người xem) KHÔNG trả về thông tin người đặt.
 */
class LichSan
{
    public function __construct(private BangGia $bangGia, private DatSanService $datSan)
    {
    }

    public function tuan(San $san, CarbonInterface $tuNgay, int $soNgay = 7, bool $chiTiet = false): array
    {
        $this->datSan->nhaChoQuaHan();

        $tu = Carbon::parse($tuNgay)->startOfDay();
        $den = $tu->copy()->addDays($soNgay - 1);

        // Mọi ô đang bị chiếm của sân trong khoảng ngày: [ngay][o] => [dat_san_id, trang_thai]
        $chiem = [];
        DB::table('o_lich')
            ->join('dat_san', 'dat_san.id', '=', 'o_lich.dat_san_id')
            // Sân này bận nếu chính nó HOẶC một sân con của nó đang bị chiếm
            ->whereIn('o_lich.san_id', array_merge([$san->id], $san->idsSanCon()))
            ->whereBetween('o_lich.ngay', [$tu->toDateString(), $den->toDateString()])
            ->get(['o_lich.ngay', 'o_lich.o', 'dat_san.id', 'dat_san.trang_thai'])
            ->each(function ($r) use (&$chiem) {
                $chiem[substr((string) $r->ngay, 0, 10)][(int) $r->o] = [(int) $r->id, $r->trang_thai];
            });

        $donIds = collect($chiem)->flatMap(fn ($ds) => array_column($ds, 0))->unique()->values();
        $dons = $chiTiet && $donIds->isNotEmpty()
            ? DatSan::with(['khach', 'san'])->whereIn('id', $donIds)->get()->keyBy('id')
            : collect();

        $ngays = [];
        for ($d = $tu->copy(); $d->lte($den); $d->addDay()) {
            $khoa = $d->toDateString();
            $khungs = [];
            foreach ($this->bangGia->khungGioTrongNgay($san, $d) as $k) {
                $trangThai = 'trong';
                $donId = null;
                for ($p = $k->phutBatDau(); $p < $k->phutKetThuc(); $p += ThoiGian::BUOC_PHUT) {
                    if (isset($chiem[$khoa][intdiv($p, ThoiGian::BUOC_PHUT)])) {
                        [$donId, $tt] = $chiem[$khoa][intdiv($p, ThoiGian::BUOC_PHUT)];
                        $trangThai = $tt === 'da_dat' ? 'da_dat' : 'dang_giu';
                        break;
                    }
                }
                if ($trangThai === 'trong' && $d->copy()->addMinutes($k->phutBatDau())->lte(now())) {
                    $trangThai = 'qua_gio';
                }
                $khungs[] = [
                    'khung' => $k,
                    'trang_thai' => $trangThai,
                    'don' => $chiTiet && $donId ? $dons->get($donId) : null,
                ];
            }
            $ngays[] = [
                'ngay' => $d->copy(),
                'loai' => $this->bangGia->loaiNgay($san, $d),
                'khungs' => $khungs,
            ];
        }

        return $ngays;
    }
}
