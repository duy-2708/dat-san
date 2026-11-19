<?php

namespace App\Console\Commands;

use App\Models\DatSan;
use App\Notifications\NhacLichDa;
use Illuminate\Console\Command;

class NhacLich extends Command
{
    protected $signature = 'dat-san:nhac-lich {--truoc=120 : Nhắc trước giờ đá bao nhiêu phút}';

    protected $description = 'Gửi email nhắc lịch cho khách sắp tới giờ đá';

    public function handle(): int
    {
        $truoc = (int) $this->option('truoc');
        $so = 0;

        DatSan::with('khach', 'san')
            ->where('trang_thai', 'da_dat')
            ->whereNotNull('khach_id')
            ->whereNull('da_nhac_lich_luc')
            ->whereBetween('ngay', [today()->toDateString(), today()->addDay()->toDateString()])
            ->get()
            ->filter(fn (DatSan $d) => $d->batDauLuc()->isFuture() && now()->diffInMinutes($d->batDauLuc()) <= $truoc)
            ->each(function (DatSan $d) use (&$so) {
                $d->khach->notify(new NhacLichDa($d));
                $d->forceFill(['da_nhac_lich_luc' => now()])->save(); // không nhắc 2 lần
                $so++;
            });

        $this->info("Đã gửi {$so} email nhắc lịch.");

        return self::SUCCESS;
    }
}
