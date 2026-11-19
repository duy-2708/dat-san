<?php

namespace Database\Seeders;

use App\Models\GoiThueBao;
use App\Models\User;
use App\Services\ThueBaoService;
use Illuminate\Database\Seeder;

/** 1 đơn đã thanh toán (chusan1) + 1 đơn đang chờ admin xác nhận (chusan2). */
class ThueBaoMauSeeder extends Seeder
{
    public function run(ThueBaoService $sv): void
    {
        $admin = User::where('email', 'admin@datsan.test')->first();
        $c1 = User::where('email', 'chusan1@datsan.test')->first();
        $c2 = User::where('email', 'chusan2@datsan.test')->first();
        $goi = GoiThueBao::where('ten', 'Tiêu chuẩn')->first();
        if (! $admin || ! $c1 || ! $c2 || ! $goi) {
            return;
        }
        $sv->giaHanTay($c1, $admin, 30, $goi, 399000, 'Chuyển khoản tháng trước');
        $sv->taoDon($c2, $goi, 3);
    }
}
