<?php

use App\Http\Controllers\Admin\ChuSanController;
use App\Http\Controllers\Admin\TaiKhoanController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\BangDieuKhienController;
use App\Http\Controllers\HoSo\SoDienThoaiController;
use App\Http\Controllers\QuanLy\NhanVienController;
use Illuminate\Support\Facades\Route;

/*
| Các route đăng nhập/đăng ký/quên mật khẩu/xác thực email do Fortify tự đăng ký:
| /login, /logout, /register, /forgot-password, /reset-password/{token},
| /email/verify, /email/verify/{id}/{hash}, /email/verification-notification
| Gõ `php artisan route:list` để xem đầy đủ.
*/

Route::view('/', 'trang-chu')->name('trang-chu'); // giai đoạn 4 thay bằng trang chủ thật

// ---- Đăng nhập Google ----
Route::middleware('guest')->group(function () {
    Route::get('/auth/google/redirect', [GoogleController::class, 'chuyenHuong'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'xuLy'])->name('google.callback');
});

// ---- Phải đăng nhập + đã xác thực email ----
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ho-so/so-dien-thoai', [SoDienThoaiController::class, 'create'])->name('ho-so.so-dien-thoai');
    Route::post('/ho-so/so-dien-thoai', [SoDienThoaiController::class, 'store'])->name('ho-so.so-dien-thoai.store');

    Route::middleware('co_sdt')->group(function () {
        Route::get('/bang-dieu-khien', BangDieuKhienController::class)->name('bang-dieu-khien');

        // ---- ADMIN ----
        Route::middleware('vai_tro:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/chu-san', [ChuSanController::class, 'index'])->name('chu-san.index');
            Route::get('/chu-san/tao', [ChuSanController::class, 'create'])->name('chu-san.create');
            Route::post('/chu-san', [ChuSanController::class, 'store'])->name('chu-san.store');

            Route::get('/tai-khoan', [TaiKhoanController::class, 'index'])->name('tai-khoan.index');
            Route::patch('/tai-khoan/{user}/khoa', [TaiKhoanController::class, 'khoa'])->name('tai-khoan.khoa');
            Route::patch('/tai-khoan/{user}/mo-khoa', [TaiKhoanController::class, 'moKhoa'])->name('tai-khoan.mo-khoa');
        });

        // ---- CHỦ SÂN + NHÂN VIÊN ----
        Route::middleware('vai_tro:chu_san,nhan_vien')->prefix('quan-ly')->name('quan-ly.')->group(function () {
            Route::view('/', 'quan-ly.tong-quan')->name('tong-quan');

            // Chỉ chủ sân
            Route::middleware('vai_tro:chu_san')->group(function () {
                Route::get('/nhan-vien', [NhanVienController::class, 'index'])->name('nhan-vien.index');
                Route::get('/nhan-vien/tao', [NhanVienController::class, 'create'])->name('nhan-vien.create');
                Route::post('/nhan-vien', [NhanVienController::class, 'store'])->name('nhan-vien.store');
                Route::patch('/nhan-vien/{nhanVien}/khoa', [NhanVienController::class, 'khoa'])->name('nhan-vien.khoa');
            });
        });

        // ---- KHÁCH ----
        Route::middleware('vai_tro:khach')->name('khach.')->group(function () {
            Route::view('/tai-khoan', 'khach.tai-khoan')->name('tai-khoan');
        });
    });
});
