<?php

use App\Http\Controllers\Admin\CaiDatController;
use App\Http\Controllers\Admin\ChuSanController;
use App\Http\Controllers\Admin\GoiThueBaoController;
use App\Http\Controllers\Admin\ThueBaoController;
use App\Http\Controllers\QuanLy\GoiController;
use App\Http\Controllers\Admin\TaiKhoanController;
use App\Http\Controllers\Admin\NhatKyController;
use App\Http\Controllers\HoSo\BaoMatController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\BangDieuKhienController;
use App\Http\Controllers\HoSo\SoDienThoaiController;
use App\Http\Controllers\CauThuController;
use App\Http\Controllers\DatSanController;
use App\Http\Controllers\DoiController;
use App\Http\Controllers\GiaiDauController;
use App\Http\Controllers\QuanLy\GiaiDauController as QuanLyGiaiDauController;
use App\Http\Controllers\DiaChiController;
use App\Http\Controllers\KeoController;
use App\Http\Controllers\KhachController;
use App\Http\Controllers\QuanLy\KeoController as QuanLyKeoController;
use App\Http\Controllers\QuanLy\DatSanController as QuanLyDatSanController;
use App\Http\Controllers\QuanLy\ThanhToanController;
use App\Http\Controllers\SanCongKhaiController;
use App\Http\Controllers\TrangChuController;
use App\Http\Controllers\WebhookController;
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

Route::get('/', [TrangChuController::class, 'index'])->name('trang-chu');

// Cổng thanh toán gọi về (không CSRF, không đăng nhập; kiểm chữ ký bên trong)
Route::post('/webhook/{cong}/{token}', [WebhookController::class, 'nhan'])->middleware('throttle:120,1')->name('webhook.nhan');
Route::get('/dia-chi/xa-phuong', [DiaChiController::class, 'xaPhuong'])->name('dia-chi.xa-phuong');
Route::get('/san/{san:slug}', [SanCongKhaiController::class, 'show'])->name('san.show'); // ai cũng xem được lịch trống/đã đặt
Route::get('/keo', [KeoController::class, 'index'])->name('keo.index');
Route::get('/keo/{keo}', [KeoController::class, 'show'])->whereNumber('keo')->name('keo.show');
Route::get('/doi', [DoiController::class, 'index'])->name('doi.index');
Route::get('/doi/{doi}', [DoiController::class, 'show'])->name('doi.show');
Route::get('/cau-thu/{user}', [CauThuController::class, 'show'])->whereNumber('user')->name('cau-thu.show');
Route::get('/giai-dau', [GiaiDauController::class, 'index'])->name('giai-dau.index');
Route::get('/giai-dau/{giaiDau}', [GiaiDauController::class, 'show'])->name('giai-dau.show');

// ---- Đăng nhập Google ----
Route::middleware('guest')->group(function () {
    Route::get('/auth/google/redirect', [GoogleController::class, 'chuyenHuong'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'xuLy'])->name('google.callback');
});

// ---- Phải đăng nhập + đã xác thực email ----
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ho-so/so-dien-thoai', [SoDienThoaiController::class, 'create'])->name('ho-so.so-dien-thoai');
    Route::post('/ho-so/so-dien-thoai', [SoDienThoaiController::class, 'store'])->name('ho-so.so-dien-thoai.store');
    Route::get('/tai-khoan/bao-mat', [BaoMatController::class, 'show'])->name('bao-mat');
    Route::get('/thong-bao', [\App\Http\Controllers\ThongBaoController::class, 'index'])->name('thong-bao.index');
    Route::get('/thong-bao/{id}', [\App\Http\Controllers\ThongBaoController::class, 'mo'])->name('thong-bao.mo');
    Route::post('/thong-bao/doc-het', [\App\Http\Controllers\ThongBaoController::class, 'docHet'])->name('thong-bao.doc-het');

    Route::middleware('co_sdt')->group(function () {
        Route::get('/bang-dieu-khien', BangDieuKhienController::class)->name('bang-dieu-khien');

        // ---- ADMIN ----
        Route::middleware(['vai_tro:admin', 'admin_2fa'])->prefix('admin')->name('admin.')->group(function () {
            Route::get('/nhat-ky', [NhatKyController::class, 'index'])->name('nhat-ky');
            Route::get('/duyet-go-phat', [\App\Http\Controllers\Admin\DuyetGoPhatController::class, 'index'])->name('duyet-go-phat');
            Route::patch('/duyet-go-phat/{yeuCau}', [\App\Http\Controllers\Admin\DuyetGoPhatController::class, 'xuLy'])->name('duyet-go-phat.xu-ly');
            Route::get('/chu-san', [ChuSanController::class, 'index'])->name('chu-san.index');
            Route::get('/chu-san/tao', [ChuSanController::class, 'create'])->name('chu-san.create');
            Route::post('/chu-san', [ChuSanController::class, 'store'])->name('chu-san.store');

            Route::get('/tai-khoan', [TaiKhoanController::class, 'index'])->name('tai-khoan.index');
            Route::patch('/tai-khoan/{user}/khoa', [TaiKhoanController::class, 'khoa'])->name('tai-khoan.khoa');
            Route::patch('/tai-khoan/{user}/mo-khoa', [TaiKhoanController::class, 'moKhoa'])->name('tai-khoan.mo-khoa');
            Route::patch('/tai-khoan/{user}/go-han-che', [TaiKhoanController::class, 'goHanChe'])->name('tai-khoan.go-han-che');
            Route::patch('/chu-san/{user}/gia-han', [ChuSanController::class, 'giaHan'])->name('chu-san.gia-han');

            Route::get('/goi', [GoiThueBaoController::class, 'index'])->name('goi.index');
            Route::post('/goi', [GoiThueBaoController::class, 'store'])->name('goi.store');
            Route::put('/goi/{goi}', [GoiThueBaoController::class, 'update'])->name('goi.update');

            Route::get('/thue-bao', [ThueBaoController::class, 'index'])->name('thue-bao.index');
            Route::patch('/thue-bao/{thueBao}/xac-nhan', [ThueBaoController::class, 'xacNhan'])->name('thue-bao.xac-nhan');
            Route::patch('/thue-bao/{thueBao}/huy', [ThueBaoController::class, 'huy'])->name('thue-bao.huy');

            Route::get('/cai-dat', [CaiDatController::class, 'edit'])->name('cai-dat.edit');
            Route::put('/cai-dat', [CaiDatController::class, 'update'])->name('cai-dat.update');
        });

        // ---- CHỦ SÂN + NHÂN VIÊN ----
        Route::middleware(['vai_tro:chu_san,nhan_vien', 'con_han_goi'])->prefix('quan-ly')->name('quan-ly.')->group(function () {
            Route::get('/', [\App\Http\Controllers\QuanLy\TongQuanController::class, 'index'])->name('tong-quan');

            // Gói dịch vụ: vẫn vào được khi đã hết hạn (middleware con_han_goi cho qua)
            Route::middleware('vai_tro:chu_san')->group(function () {
                Route::get('/goi', [GoiController::class, 'index'])->name('goi.index');
                Route::post('/goi', [GoiController::class, 'store'])->name('goi.store');
                Route::get('/goi/don/{thueBao}', [GoiController::class, 'show'])->name('goi.show');
                Route::patch('/goi/don/{thueBao}/huy', [GoiController::class, 'huy'])->name('goi.huy');
            });

            // Sân: nhân viên xem được danh sách & bảng giá; sửa thì Policy chỉ cho chủ sân
            Route::get('/san', [SanController::class, 'index'])->name('san.index');
            Route::get('/san/{san}/khung-gio', [KhungGioController::class, 'index'])->name('san.khung-gio.index');

            // Đặt sân phía sân (chủ + nhân viên): xem đơn, lịch, xác nhận cọc, gia hạn, đặt tại chỗ
            Route::get('/dat-san', [QuanLyDatSanController::class, 'index'])->name('dat-san.index');
            Route::get('/co-dinh', [\App\Http\Controllers\QuanLy\CoDinhController::class, 'index'])->name('co-dinh.index');
            Route::post('/co-dinh', [\App\Http\Controllers\QuanLy\CoDinhController::class, 'store'])->name('co-dinh.store');
            Route::patch('/co-dinh/{chuoi}/huy', [\App\Http\Controllers\QuanLy\CoDinhController::class, 'huyKhoang'])->name('co-dinh.huy');
            Route::patch('/co-dinh/{chuoi}/xoa', [\App\Http\Controllers\QuanLy\CoDinhController::class, 'xoa'])->name('co-dinh.xoa');
            Route::get('/san/{san}/lich', [QuanLyDatSanController::class, 'lich'])->name('san.lich');
            Route::post('/san/{san}/dat-tai-cho', [QuanLyDatSanController::class, 'datTaiCho'])->name('san.dat-tai-cho');
            Route::patch('/dat-san/{datSan}/xac-nhan-coc', [QuanLyDatSanController::class, 'xacNhanCoc'])->name('dat-san.xac-nhan-coc');
            Route::patch('/dat-san/{datSan}/gia-han', [QuanLyDatSanController::class, 'giaHan'])->name('dat-san.gia-han');
            Route::patch('/dat-san/{datSan}/da-thu', [QuanLyDatSanController::class, 'daThu'])->name('dat-san.da-thu');
            Route::patch('/dat-san/{datSan}/khong-thay-tien', [QuanLyDatSanController::class, 'khongThayTien'])->name('dat-san.khong-thay-tien');
            Route::patch('/dat-san/{datSan}/khong-tra-du', [QuanLyDatSanController::class, 'khongTraDu'])->name('dat-san.khong-tra-du'); // Policy: chỉ chủ sân
            Route::patch('/dat-san/{datSan}/huy', [QuanLyDatSanController::class, 'huy'])->name('dat-san.huy');       // Policy: chỉ chủ sân
            Route::patch('/dat-san/{datSan}/da-hoan', [QuanLyDatSanController::class, 'daHoan'])->name('dat-san.da-hoan'); // Policy: chỉ chủ sân
            Route::patch('/coc-keo/{giaoDich}/xac-nhan', [QuanLyKeoController::class, 'xacNhanCoc'])->name('coc-keo.xac-nhan');
            Route::patch('/coc-keo/{giaoDich}/khong-thay-tien', [QuanLyKeoController::class, 'khongThayTienCoc'])->name('coc-keo.khong-thay-tien');
            Route::patch('/keo/{keo}/chot', [QuanLyKeoController::class, 'chot'])->name('keo.chot');
            Route::patch('/keo/{keo}/thu-tien', [QuanLyKeoController::class, 'ghiThuTien'])->name('keo.thu-tien'); // chủ sân + nhân viên
            Route::patch('/tham-gia-keo/{thamGia}/mien-phat', [QuanLyKeoController::class, 'mienPhat'])->name('keo.mien-phat');   // chỉ chủ sân
            Route::patch('/han-che/{user}/go', [QuanLyKeoController::class, 'goHanChe'])->name('han-che.go');                      // chỉ chủ sân
            Route::get('/dat-san/{datSan}/tao-keo', [QuanLyKeoController::class, 'create'])->name('keo.create');
            Route::post('/dat-san/{datSan}/keo', [QuanLyKeoController::class, 'store'])->name('keo.store');
            Route::patch('/keo/{keo}/huy', [QuanLyKeoController::class, 'huy'])->name('keo.huy');                                   // chỉ chủ sân
            Route::patch('/coc-keo/{giaoDich}/da-hoan', [QuanLyKeoController::class, 'daHoanCoc'])->name('coc-keo.da-hoan');         // chỉ chủ sân

            // Giải đấu: nhân viên cũng xem, thêm trận và nhập tỉ số (tạo/duyệt/kết thúc giải chỉ chủ sân)
            Route::get('/giai-dau', [QuanLyGiaiDauController::class, 'index'])->name('giai-dau.index');
            Route::get('/giai-dau/tao', [QuanLyGiaiDauController::class, 'create'])->name('giai-dau.create')->middleware('vai_tro:chu_san');
            Route::get('/giai-dau/{giaiDau}', [QuanLyGiaiDauController::class, 'show'])->name('giai-dau.show');
            Route::post('/giai-dau/{giaiDau}/tran', [QuanLyGiaiDauController::class, 'themTran'])->name('giai-dau.tran');
            Route::patch('/tran-dau/{tranDau}', [QuanLyGiaiDauController::class, 'ketQua'])->name('giai-dau.ket-qua');

            // Chỉ chủ sân
            Route::middleware('vai_tro:chu_san')->group(function () {
                Route::get('/san/tao', [SanController::class, 'create'])->name('san.create');
                Route::post('/san', [SanController::class, 'store'])->name('san.store');
                Route::get('/san/{san}/sua', [SanController::class, 'edit'])->name('san.edit');
                Route::put('/san/{san}', [SanController::class, 'update'])->name('san.update');
                Route::patch('/san/{san}/trang-thai', [SanController::class, 'doiTrangThai'])->name('san.trang-thai');
                Route::delete('/san/{san}', [SanController::class, 'destroy'])->name('san.destroy');
                Route::delete('/co-so/{coSo}', [SanController::class, 'xoaCoSo'])->name('co-so.destroy');
                Route::delete('/san/{san}/xoa-han', [SanController::class, 'xoaHan'])->name('san.xoa-han');

                Route::post('/san/{san}/anh', [AnhSanController::class, 'store'])->name('san.anh.store');
                Route::patch('/anh-san/{anh}/len-dau', [AnhSanController::class, 'lenDau'])->name('anh-san.len-dau');
                Route::delete('/anh-san/{anh}', [AnhSanController::class, 'destroy'])->name('anh-san.destroy');

                Route::post('/san/{san}/khung-gio', [KhungGioController::class, 'store'])->name('san.khung-gio.store');
                Route::post('/san/{san}/khung-gio/tao-nhanh', [KhungGioController::class, 'taoNhanh'])->name('san.khung-gio.tao-nhanh');
                Route::post('/san/{san}/khung-gio/tao-bu', [KhungGioController::class, 'taoBu'])->name('san.khung-gio.tao-bu');
                Route::put('/san/{san}/khung-gio', [KhungGioController::class, 'luuTatCa'])->name('san.khung-gio.luu-tat-ca');
                Route::put('/khung-gio/{khungGio}', [KhungGioController::class, 'update'])->name('khung-gio.update');
                Route::delete('/khung-gio/{khungGio}', [KhungGioController::class, 'destroy'])->name('khung-gio.destroy');

                Route::post('/giai-dau', [QuanLyGiaiDauController::class, 'store'])->name('giai-dau.store');
                Route::patch('/giai-dau/{giaiDau}/dong-dang-ky', [QuanLyGiaiDauController::class, 'dongDangKy'])->name('giai-dau.dong-dang-ky');
                Route::patch('/giai-dau/{giaiDau}/ket-thuc', [QuanLyGiaiDauController::class, 'ketThuc'])->name('giai-dau.ket-thuc');
                Route::patch('/dang-ky-giai/{dangKy}', [QuanLyGiaiDauController::class, 'duyet'])->name('giai-dau.duyet');

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

        // ---- Hồ sơ cá nhân ----
        Route::get('/ho-so', [CauThuController::class, 'edit'])->name('ho-so.edit');
        Route::put('/ho-so', [CauThuController::class, 'update'])->name('ho-so.update');

        // ---- Kèo: cọc + điểm danh (người tạo kèo hoặc phía sân; kiểm tra trong service) ----
        Route::get('/keo/coc/{giaoDich}', [KeoController::class, 'coc'])->name('keo.coc');
        Route::post('/keo/coc/{giaoDich}/da-chuyen', [KeoController::class, 'daChuyenCoc'])->name('keo.coc.da-chuyen');
        Route::post('/keo/{keo}/diem-danh', [KeoController::class, 'diemDanh'])->name('keo.diem-danh');
        Route::post('/keo/{keo}/ket-qua', [KeoController::class, 'baoKetQua'])->name('keo.ket-qua');
        Route::get('/keo/tao-truoc', [KeoController::class, 'taoTruoc'])->name('keo.tao-truoc');      // khách hoặc phía sân
        Route::get('/keo/san-tao', [KeoController::class, 'sanTaoKeo'])->name('keo.san-tao');        // phía sân: chọn sân/ngày/khung
        Route::post('/keo/truoc', [KeoController::class, 'luuTruoc'])->name('keo.luu-truoc');
        Route::patch('/xin-keo/{xinKeo}', [KeoController::class, 'duyetXin'])->name('keo.duyet-xin');     // chủ kèo hoặc phía sân
        Route::post('/keo/{keo}/them-doi', [KeoController::class, 'themDoi'])->name('keo.them-doi');      // phía sân // đại diện 2 bên (kể cả chủ sân tạo kèo hộ)

        // ---- Trang thanh toán của 1 đơn (Policy: khách của đơn hoặc phía sân) ----
        Route::get('/dat-san/{datSan}/thanh-toan', [DatSanController::class, 'thanhToan'])->name('dat-san.thanh-toan');
        Route::get('/dat-san/{datSan}/trang-thai', [DatSanController::class, 'trangThai'])->name('dat-san.trang-thai');
        Route::get('/dat-san/{datSan}/tra-con', [DatSanController::class, 'thanhToanConLai'])->name('dat-san.tra-con');
        Route::post('/dat-san/{datSan}/huy', [DatSanController::class, 'huy'])->name('dat-san.huy');

        // ---- KHÁCH ----
        Route::middleware('vai_tro:khach')->name('khach.')->group(function () {
            Route::get('/tai-khoan', [KhachController::class, 'taiKhoan'])->name('tai-khoan');
            Route::get('/thanh-toan-cua-toi', [KhachController::class, 'thanhToan'])->name('thanh-toan');
            Route::get('/keo/tao', [KeoController::class, 'create'])->name('keo.create');
            Route::post('/keo', [KeoController::class, 'store'])->name('keo.store');
            Route::post('/keo/{keo}/nhan', [KeoController::class, 'nhan'])->name('keo.nhan');
            Route::post('/keo/{keo}/xin', [KeoController::class, 'xin'])->name('keo.xin');
            Route::post('/keo/{keo}/tham-gia', [KeoController::class, 'thamGia'])->name('keo.tham-gia');
            Route::post('/keo/{keo}/thanh-vien', [KeoController::class, 'themThanhVien'])->name('keo.thanh-vien');
            Route::post('/keo/{keo}/huy', [KeoController::class, 'huy'])->name('keo.huy');

            // Đội (đội trưởng / thành viên; kiểm tra trong DoiService)
            Route::get('/doi-moi', [DoiController::class, 'create'])->name('doi.create');
            Route::post('/doi', [DoiController::class, 'store'])->name('doi.store');
            Route::put('/doi/{doi}', [DoiController::class, 'update'])->name('doi.update');
            Route::post('/doi/{doi}/xin-vao', [DoiController::class, 'xinVao'])->name('doi.xin-vao');
            Route::post('/doi/{doi}/thanh-vien', [DoiController::class, 'themThanhVien'])->name('doi.thanh-vien');
            Route::post('/doi/{doi}/doi-truong', [DoiController::class, 'chuyenDoiTruong'])->name('doi.doi-truong');
            Route::patch('/thanh-vien-doi/{thanhVien}/duyet', [DoiController::class, 'duyet'])->name('doi.duyet');
            Route::delete('/thanh-vien-doi/{thanhVien}', [DoiController::class, 'roi'])->name('doi.roi');
            Route::post('/giai-dau/{giaiDau}/dang-ky', [GiaiDauController::class, 'dangKy'])->name('giai-dau.dang-ky');
            Route::post('/dat-san', [DatSanController::class, 'store'])->name('dat-san.store');
            Route::post('/dat-san/{datSan}/da-chuyen', [DatSanController::class, 'daChuyen'])->name('dat-san.da-chuyen');
        });
    });
});
