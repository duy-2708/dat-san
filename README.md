# Đặt Sân — Giai đoạn 1: Khung Laravel + tài khoản/phân quyền

> **Cách nhanh:** chạy `powershell -ExecutionPolicy Bypass -File D:\DuAnCaNhan\_cai-dat\cai-dat-giai-doan-1.ps1` trong Terminal của Laragon — script làm hết các bước dưới đây và đẩy lên GitHub.

Thư mục này là **bộ file chép đè** lên một project Laravel 13 mới tạo. Làm theo đúng thứ tự dưới đây.

## 1. Chuẩn bị (một lần)

- **Laragon** bản mới (có PHP ≥ 8.3, MySQL/MariaDB, Composer). Mở Terminal của Laragon, gõ `php -v` → phải ≥ 8.3.
  XAMPP thường đi kèm PHP 8.2 → Laravel 13 không chạy.
- Tạo CSDL rỗng tên `dat_san`, bảng mã `utf8mb4_unicode_ci` (HeidiSQL trong Laragon → chuột phải → Create new → Database).

## 2. Tạo project và chép code

```bash
cd C:\laragon\www
composer create-project laravel/laravel datsan
cd datsan
composer require laravel/fortify laravel/socialite
php artisan fortify:install          # tạo config + FortifyServiceProvider + đăng ký provider
```

Sau đó **chép toàn bộ nội dung thư mục này đè vào `C:\laragon\www\datsan`** (chọn "Replace" khi Windows hỏi). Các file bị ghi đè là có chủ ý:
`bootstrap/app.php`, `config/fortify.php`, `config/services.php`, `app/Models/User.php`, `app/Providers/*`, `app/Actions/Fortify/*`,
`database/migrations/0001_01_01_000000_create_users_table.php`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`, `routes/web.php`, `phpunit.xml`.

Rồi:

1. Mở `.env`, chép các dòng trong `.env.mau` vào (giữ `APP_KEY`).
2. Mở `config/app.php`, đổi `'timezone' => 'UTC'` thành `'timezone' => 'Asia/Ho_Chi_Minh'` (giờ đặt sân phải theo giờ Việt Nam).
3. Chạy:

```bash
php artisan migrate:fresh --seed     # tạo toàn bộ bảng + dữ liệu mẫu
php artisan test                     # phải XANH hết mới sang giai đoạn 2
```

Laragon tự tạo tên miền `http://datsan.test` (bấm **Reload** Apache/Nginx). Hoặc chạy `php artisan serve` → `http://127.0.0.1:8000`.

## 3. Tài khoản mẫu (mật khẩu `12345678`)

| Email | Vai trò | Ghi chú |
|---|---|---|
| admin@datsan.test | Admin | |
| chusan1@datsan.test | Chủ sân | gói Tiêu chuẩn, còn 60 ngày; nhân viên: nhanvien1@datsan.test |
| chusan2@datsan.test | Chủ sân | gói Cơ bản, còn 20 ngày; nhân viên: nhanvien2@datsan.test |
| chusan3@datsan.test | Chủ sân | **đã hết hạn** (dùng ở giai đoạn 8) |
| khach1..khach5@datsan.test | Khách | |
| khach6@datsan.test | Khách | **bị khoá** — thử đăng nhập sẽ bị chặn |

Đăng ký tài khoản mới: email xác thực được ghi vào `storage/logs/laravel.log` (tìm dòng `email/verify/` và dán link vào trình duyệt).

## 4. Đọc code theo thứ tự này (từ PHP thuần sang Laravel)

| PHP thuần bạn quen | Laravel | File trong project |
|---|---|---|
| `include 'config/db.php'` + PDO | `.env` + Eloquent Model | `app/Models/User.php` |
| File `.sql` tạo bảng | **Migration** (code PHP tạo bảng, chạy được lại, có lịch sử) | `database/migrations/*` |
| File `.sql` INSERT mẫu | **Seeder** | `database/seeders/*` |
| `dangnhap.php` tự viết, `password_hash` | **Fortify** lo sẵn; ta chỉ khai báo view & quy tắc | `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/CreateNewUser.php` |
| `if ($_SESSION['vai_tro'] != 'admin') die();` ở đầu mỗi file | **Middleware** gắn vào nhóm route | `app/Http/Middleware/KiemTraVaiTro.php`, `routes/web.php` |
| `if ($san['ma_chu'] != $_SESSION['id'])` | **Policy** — quyền trên từng bản ghi | `app/Policies/UserPolicy.php` |
| `$_POST` + tự kiểm tra | `$request->validate([...])` | `app/Http/Controllers/QuanLy/NhanVienController.php` |
| `header.php` / `footer.php` | Blade layout `@extends` / `@yield` | `resources/views/layouts/app.blade.php` |
| Bấm thử bằng tay | **Test tự động** chạy bằng `php artisan test` | `tests/Feature/GiaiDoan1/*` |

Ba điểm bảo mật nên đọc kỹ:
- `User::$fillable` **không** có `vai_tro`, `chu_san_id`, `trang_thai` → gửi form kèm `vai_tro=admin` cũng vô tác dụng (có test chứng minh).
- `NhanVienController::store` gán `chu_san_id` từ người đang đăng nhập, không lấy từ form.
- Khoá API PayOS dùng cast `encrypted` → trong CSDL chỉ thấy chuỗi mã hoá (có test).

## 5. Giai đoạn 1 gồm

- Toàn bộ migration của **cả dự án** (thiết kế xong trước, xem `docs/00-phan-tich-va-thiet-ke-csdl.md`).
- Đăng ký (khách), đăng nhập, xác thực email bắt buộc, quên mật khẩu, đăng nhập Google, bắt bổ sung SĐT cho tài khoản Google.
- 1 email (kể cả biến thể Gmail) + 1 SĐT = 1 tài khoản, ràng buộc UNIQUE ở CSDL.
- 4 vai trò; admin tạo chủ sân (kèm hồ sơ, hạn dùng), khoá/mở tài khoản; chủ sân tạo/khoá nhân viên của mình.
- Tài khoản bị khoá bị đăng xuất ngay ở request kế tiếp; khoá chủ sân thì nhân viên cũng bị chặn.
- 32 test (30 Feature + 2 Unit) — chạy bằng `php artisan test`.

## Bảo mật & triển khai
- [docs/01-bao-mat.md](docs/01-bao-mat.md) — đã cài gì, bật xác minh 2 bước, nhật ký, sao lưu & phục hồi.
- [docs/02-trien-khai-may-chu.md](docs/02-trien-khai-may-chu.md) — đưa lên VPS bằng 1 lệnh (`trien-khai/cai-may-chu.sh`), Cloudflare, HTTPS, UptimeRobot, sao lưu Google Drive.
