<?php

namespace App\Console\Commands;

use App\Services\KeoService;
use Illuminate\Console\Command;

class QuetKeo extends Command
{
    protected $signature = 'keo:quet';

    protected $description = 'Nhả người chưa cọc kèo quá hạn; hạn chế bên không xác nhận kết quả sau 60 phút';

    public function handle(KeoService $keo): int
    {
        $this->info('Nhả '.$keo->nhaCocQuaHan().' chỗ kèo quá hạn cọc.');
        $this->info('Huỷ '.$keo->xuLyKeoTruocQuaHan().' kèo đã chốt nhưng không đủ cọc.');
        $this->info('Huỷ '.$keo->quetKeoTruocMatSan().' kèo chưa đặt sân bị người khác đặt mất giờ.');
        $this->info('Hạn chế '.$keo->kiemTraHanXacNhan().' người không xác nhận kết quả.');

        return self::SUCCESS;
    }
}
