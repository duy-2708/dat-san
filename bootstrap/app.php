<?php

use App\Http\Middleware\ChanTaiKhoanBiKhoa;
use App\Http\Middleware\KiemTraVaiTro;
use App\Http\Middleware\ChuanHoaTien;
use App\Http\Middleware\YeuCauConHanGoi;
use App\Http\Middleware\YeuCauSoDienThoai;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Đặt tên ngắn để dùng trong routes/web.php
        $middleware->alias([
            'vai_tro' => KiemTraVaiTro::class,
            'co_sdt' => YeuCauSoDienThoai::class,
            'con_han_goi' => YeuCauConHanGoi::class,
        ]);

        // Chạy cho mọi request thuộc nhóm "web"
        $middleware->appendToGroup('web', ChanTaiKhoanBiKhoa::class);
        $middleware->appendToGroup('web', ChuanHoaTien::class);

        // Webhook cổng thanh toán (giai đoạn 5) không có CSRF token
        $middleware->validateCsrfTokens(except: ['webhook/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
