<?php

use App\Http\Controllers\Admin\ChuSanController;
use App\Http\Controllers\Admin\TaiKhoanController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\BangDieuKhienController;
use App\Http\Controllers\HoSo\SoDienThoaiController;
use App\Http\Controllers\DatSanController;
use App\Http\Controllers\DiaChiController;
use App\Http\Controllers\KhachController;
use App\Http\Controllers\QuanLy\DatSanController as QuanLyDatSanController;
use App\Http\Controllers\QuanLy\ThanhToanController;
use App\Http\Controllers\SanCongKhaiController;
use App\Http\Controllers\QuanLy\AnhSanController;
use App\Http\Controllers\QuanLy\KhungGioController;
use App\Http\Controllers\QuanLy\NgayLeController;
use App\Http\Controllers\QuanLy\NhanVienController;
use App\Http\Controllers\QuanLy\SanController;
use Illuminate\Support\Facades\Route;

/*
| Các route đăng nhập/đăng ký/quên mật khẩu/xác thực email do Fortify tự đăng ký:
| /login, /logout, /register, /forgot-password, /reset-password/{token},
| /email/verify, /email/verify/{id}/{hash}, /email/verification-notification
| Gõ `php artisan route:list` để xem đầy đủ.
*/

// Tạm thời: liệt kê sân đang mở. Giai đoạn 4 thay bằng trang chủ có bộ lọc + lịch hôm nay.
Route::get('/', fn () => view('trang-chu', [
    'sans' => \App\Models\San::with('anhSans', 'xaPhuong.tinhThanh')->where('trang_thai', 'hoat_dong')
        ->whereHas('chuSan.hoSoChuSan', fn ($q) => $q->where('het_han_luc', '>', now()))->orderBy('ten')->get(),
]))->name('trang-chu');
Route::get('/dia-chi/xa-phuong', [DiaChiController::class, 'xaPhuong'])->name('dia-chi.xa-phuong');
Route::get('/san/{san:slug}', [SanCongKhaiController::class, 'show'])->name('san.show'); // ai cũng xem được lịch trống/đã đặt

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

            // Sân: nhân viên xem được danh sách & bảng giá; sửa thì Policy chỉ cho chủ sân
            Route::get('/san', [SanController::class, 'index'])->name('san.index');
            Route::get('/san/{san}/khung-gio', [KhungGioController::class, 'index'])->name('san.khung-gio.index');

            // Đặt sân phía sân (chủ + nhân viên): xem đơn, lịch, xác nhận cọc, gia hạn, đặt tại chỗ
            Route::get('/dat-san', [QuanLyDatSanController::class, 'index'])->name('dat-san.index');
            Route::get('/san/{san}/lich', [QuanLyDatSanController::class, 'lich'])->name('san.lich');
            Route::post('/san/{san}/dat-tai-cho', [QuanLyDatSanController::class, 'datTaiCho'])->name('san.dat-tai-cho');
            Route::patch('/dat-san/{datSan}/xac-nhan-coc', [QuanLyDatSanController::class, 'xacNhanCoc'])->name('dat-san.xac-nhan-coc');
            Route::patch('/dat-san/{datSan}/gia-han', [QuanLyDatSanController::class, 'giaHan'])->name('dat-san.gia-han');
            Route::patch('/dat-san/{datSan}/huy', [QuanLyDatSanController::class, 'huy'])->name('dat-san.huy');       // Policy: chỉ chủ sân
            Route::patch('/dat-san/{datSan}/da-hoan', [QuanLyDatSanController::class, 'daHoan'])->name('dat-san.da-hoan'); // Policy: chỉ chủ sân

            // Chỉ chủ sân
            Route::middleware('vai_tro:chu_san')->group(function () {
                Route::get('/san/tao', [SanController::class, 'create'])->name('san.create');
                Route::post('/san', [SanController::class, 'store'])->name('san.store');
                Route::get('/san/{san}/sua', [SanController::class, 'edit'])->name('san.edit');
                Route::put('/san/{san}', [SanController::class, 'update'])->name('san.update');
                Route::patch('/san/{san}/trang-thai', [SanController::class, 'doiTrangThai'])->name('san.trang-thai');
                Route::delete('/san/{san}', [SanController::class, 'destroy'])->name('san.destroy');

                Route::post('/san/{san}/anh', [AnhSanController::class, 'store'])->name('san.anh.store');
                Route::patch('/anh-san/{anh}/len-dau', [AnhSanController::class, 'lenDau'])->name('anh-san.len-dau');
                Route::delete('/anh-san/{anh}', [AnhSanController::class, 'destroy'])->name('anh-san.destroy');

                Route::post('/san/{san}/khung-gio', [KhungGioController::class, 'store'])->name('san.khung-gio.store');
                Route::post('/san/{san}/khung-gio/tao-nhanh', [KhungGioController::class, 'taoNhanh'])->name('san.khung-gio.tao-nhanh');
                Route::put('/khung-gio/{khungGio}', [KhungGioController::class, 'update'])->name('khung-gio.update');
                Route::delete('/khung-gio/{khungGio}', [KhungGioController::class, 'destroy'])->name('khung-gio.destroy');

                Route::get('/thanh-toan', [ThanhToanController::class, 'edit'])->name('thanh-toan.edit');
                Route::put('/thanh-toan', [ThanhToanController::class, 'update'])->name('thanh-toan.update');

                Route::get('/ngay-le', [NgayLeController::class, 'index'])->name('ngay-le.index');
                Route::post('/ngay-le', [NgayLeController::class, 'store'])->name('ngay-le.store');
                Route::delete('/ngay-le/{ngayLe}', [NgayLeController::class, 'destroy'])->name('ngay-le.destroy');

                Route::get('/nhan-vien', [NhanVienController::class, 'index'])->name('nhan-vien.index');
                Route::get('/nhan-vien/tao', [NhanVienController::class, 'create'])->name('nhan-vien.create');
                Route::post('/nhan-vien', [NhanVienController::class, 'store'])->name('nhan-vien.store');
                Route::patch('/nhan-vien/{nhanVien}/khoa', [NhanVienController::class, 'khoa'])->name('nhan-vien.khoa');
            });
        });

        // ---- Trang thanh toán của 1 đơn (Policy: khách của đơn hoặc phía sân) ----
        Route::get('/dat-san/{datSan}/thanh-toan', [DatSanController::class, 'thanhToan'])->name('dat-san.thanh-toan');
        Route::get('/dat-san/{datSan}/trang-thai', [DatSanController::class, 'trangThai'])->name('dat-san.trang-thai');
        Route::post('/dat-san/{datSan}/huy', [DatSanController::class, 'huy'])->name('dat-san.huy');

        // ---- KHÁCH ----
        Route::middleware('vai_tro:khach')->name('khach.')->group(function () {
            Route::get('/tai-khoan', [KhachController::class, 'taiKhoan'])->name('tai-khoan');
            Route::post('/dat-san', [DatSanController::class, 'store'])->name('dat-san.store');
        });
    });
});
