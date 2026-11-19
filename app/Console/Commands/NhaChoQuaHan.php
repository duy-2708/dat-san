<?php

namespace App\Console\Commands;

use App\Services\DatSanService;
use Illuminate\Console\Command;

class NhaChoQuaHan extends Command
{
    protected $signature = 'dat-san:nha-cho';

    protected $description = 'Huỷ các đơn giữ chỗ quá hạn chưa cọc, trả ô lịch cho người khác đặt';

    public function handle(DatSanService $datSan): int
    {
        $so = $datSan->nhaChoQuaHan();
        $this->info("Đã nhả {$so} đơn quá hạn.");
        $this->info('Nhắc '.$datSan->nhacBaoChuyen().' đơn khách báo đã chuyển sắp hết giữ chỗ.');

        return self::SUCCESS;
    }
}
