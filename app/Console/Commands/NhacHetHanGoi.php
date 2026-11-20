<?php

namespace App\Console\Commands;

use App\Models\CaiDat;
use App\Models\HoSoChuSan;
use App\Notifications\GoiSapHetHan;
use Illuminate\Console\Command;

class NhacHetHanGoi extends Command
{
    protected $signature = 'thue-bao:nhac {--ngay=3 : Nhắc trước bao nhiêu ngày}';

    protected $description = 'Email nhắc chủ sân sắp hết hạn gói; đánh dấu hồ sơ đã hết hạn';

    public function handle(): int
    {
        $so = 0;
        if (! CaiDat::dangMienPhi()) {
            HoSoChuSan::with('user')
                ->whereNull('da_nhac_het_han_luc')
                ->whereBetween('het_han_luc', [now(), now()->addDays((int) $this->option('ngay'))])
                ->get()
                ->each(function (HoSoChuSan $h) use (&$so) {
                    $h->user?->notify(new GoiSapHetHan($h));
                    \App\Support\BaoTin::gui($h->user, 'Gói sắp hết hạn', 'Gói dịch vụ hết hạn ngày '.$h->het_han_luc->format('d/m/Y').'. Gia hạn để khách vẫn đặt được sân.', route('quan-ly.goi.index'), 'hourglass-split');
                    $h->forceFill(['da_nhac_het_han_luc' => now()])->save(); // không nhắc 2 lần
                    $so++;
                });
        }
        // Cờ để admin lọc nhanh; quyền truy cập thực tế vẫn tính theo HoSoChuSan::conHan()
        HoSoChuSan::where('het_han_luc', '<=', now())->where('bi_khoa_do_het_han', false)->update(['bi_khoa_do_het_han' => true]);

        $this->info("Đã gửi {$so} email nhắc gia hạn.");

        return self::SUCCESS;
    }
}
