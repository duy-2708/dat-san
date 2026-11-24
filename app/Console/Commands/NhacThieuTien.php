<?php

namespace App\Console\Commands;

use App\Models\DatSan;
use App\Support\BaoTin;
use Illuminate\Console\Command;

class NhacThieuTien extends Command
{
    protected $signature = 'dat-san:nhac-thieu-tien {--sau=120 : Sau bao nhiêu phút kể từ khi hết giờ đá}';

    protected $description = 'Nhắc chủ sân xác nhận đơn online đã đá xong mà khách chưa thanh toán đủ';

    public function handle(): int
    {
        $sau = (int) $this->option('sau');
        $so = 0;

        DatSan::with('san')
            ->where('trang_thai', 'da_dat')
            ->whereNotNull('khach_id')
            ->whereNull('khong_tra_du_luc')
            ->whereNull('nhac_thieu_tien_luc')
            ->whereDate('ngay', '>=', today()->subDays(2)->toDateString())
            ->whereDate('ngay', '<=', today()->toDateString())
            ->get()
            ->filter(fn (DatSan $d) => $d->ketThucLuc()->addMinutes($sau)->lte(now()) && $d->conLaiPhai() > 0)
            ->each(function (DatSan $d) use (&$so) {
                BaoTin::gui(
                    BaoTin::phiaSan($d->san->chu_san_id),
                    'Khách chưa thanh toán đủ '.$d->ma_don,
                    $d->moTaNgan().' đã hết giờ đá. Còn thiếu '.number_format($d->conLaiPhai(), 0, ',', '.').'đ. Nếu đã thu đủ thì ghi nhận trong Đơn đặt; nếu chưa đủ thì bấm "Không thanh toán đủ".',
                    route('quan-ly.dat-san.index'),
                    'exclamation-triangle'
                );
                $d->forceFill(['nhac_thieu_tien_luc' => now()])->save(); // không nhắc 2 lần
                $so++;
            });

        $this->info("Đã nhắc {$so} đơn thiếu tiền.");

        return self::SUCCESS;
    }
}
