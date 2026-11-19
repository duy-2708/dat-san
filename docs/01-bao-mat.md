# Bảo mật & vận hành — đã cài gì, dùng thế nào

Tất cả dưới đây **miễn phí**. Đã có trong code, chạy được cả máy bạn lẫn máy chủ thật.

## 1. Đã có sẵn trong code

| Lớp bảo vệ | Chống cái gì | Ghi chú |
|---|---|---|
| Mật khẩu băm bcrypt, xác thực email, khoá tài khoản | Lộ mật khẩu, tài khoản rác | Có từ giai đoạn 1 |
| CSRF, Blade tự lọc, Eloquent | Giả mạo form, chèn mã độc (XSS), chèn SQL | Có từ đầu |
| Policy + vai trò | Chủ sân A sửa đồ của chủ sân B | Có từ đầu |
| **Giới hạn tốc độ** | Spam, dò mật khẩu, bot | Đăng nhập sai 5 lần/phút; đăng ký/quên mật khẩu 5 lần/10 phút/IP; thao tác khác 40 lần/phút/người. Quá → trang "Thao tác quá nhanh" (429) |
| **Header bảo mật** | Nhúng trang vào iframe lừa bấm, đoán kiểu file | Tự gắn mọi trang |
| **Xác minh 2 bước (2FA)** | Bị lộ mật khẩu | Menu tên → **Bảo mật tài khoản**. **Admin bắt buộc** trên máy chủ thật |
| **Nhật ký** | Tranh chấp tiền cọc, nghi bị xâm nhập | Admin → **Nhật ký**: ai làm gì, lúc nào, IP nào. Đăng nhập sai tô vàng. Giữ 180 ngày |
| **Lịch sử đăng nhập** | Phát hiện người lạ vào tài khoản | Trang Bảo mật tài khoản, 10 lần gần nhất |
| **Trang lỗi tiếng Việt** | Lộ mã nguồn / cấu hình khi có lỗi | 403, 404, 419, 429, 500, 503 |
| **Sao lưu CSDL tự động** | Mất dữ liệu (hỏng ổ, bị phá, lỡ tay xoá) | 02:30 mỗi đêm, nén .gz, giữ 14 ngày |
| **Chặn lệnh xoá CSDL** | Lỡ gõ `migrate:fresh` trên máy chủ thật | Tự chặn khi APP_ENV=production |
| **Mật khẩu mạnh khi chạy thật** | Mật khẩu dễ đoán / đã bị lộ | ≥ 8 ký tự, có chữ + số, không nằm trong danh sách mật khẩu đã lộ |
| **Không tạo tài khoản mẫu khi chạy thật** | admin@datsan.test / 12345678 | `db:seed` trên production chỉ nạp tỉnh/xã, gói, cài đặt, ngày lễ |
| **Email chạy nền (hàng đợi)** | Web chậm/treo khi Gmail lỗi | Máy bạn: CHAY-WEB mở thêm cửa sổ "hàng đợi" |

## 2. Cách dùng

### Bật xác minh 2 bước (nên làm ngay với admin & chủ sân)
1. Cài **Google Authenticator** (hoặc Microsoft Authenticator) trên điện thoại.
2. Web → bấm tên mình (góc phải) → **Bảo mật tài khoản** → nhập lại mật khẩu.
3. **Bật xác minh 2 bước** → app → dấu **+** → quét mã QR → nhập mã 6 số → **Xác nhận**.
4. Chép **8 mã dự phòng** ra giấy. Mất điện thoại thì đăng nhập bằng 1 mã dự phòng (mỗi mã dùng 1 lần).

Tài khoản đăng nhập bằng Google: bật 2 bước trong tài khoản Google.

### Xem nhật ký (admin)
Menu **Nhật ký** → lọc theo email/IP/mã đơn (`datSan#12`), loại hành động, ngày.
Thấy nhiều dòng "Đăng nhập sai" cùng 1 IP = có người dò mật khẩu → bình thường hệ thống đã tự chặn; nếu kéo dài, chặn IP đó trên Cloudflare.

### Sao lưu & phục hồi
- Tự chạy mỗi đêm. Chạy tay: `php artisan sao-luu:csdl` → file ở `storage/app/sao-luu/`.
- Phục hồi trên máy chủ:
  ```bash
  gunzip -c storage/app/sao-luu/dat_san-2026-12-01_023000.sql.gz | mariadb -u dat_san -p dat_san
  ```
- **Bản sao lưu phải nằm ở nơi khác máy chủ** (máy chủ hỏng là mất cả hai) → xem rclone ở `02-trien-khai-may-chu.md`.

### Tạo admin trên máy chủ thật
```bash
sudo -u www-data php artisan tao-admin email-cua-ban@gmail.com
```
Hỏi tên, SĐT, mật khẩu (≥ 12 ký tự, có chữ + số; gõ không hiện). Đăng nhập xong sẽ bị yêu cầu bật 2 bước.

## 3. Thói quen an toàn (không tốn tiền)
- Không gửi file `.env`, mật khẩu ứng dụng Gmail, mã dự phòng qua Zalo/Messenger.
- Mỗi người 1 tài khoản; nhân viên nghỉ → khoá tài khoản ngay.
- Cập nhật bản mới bằng `cap-nhat.sh` (tự sao lưu trước, tự kiểm lỗ hổng thư viện).
- Mỗi tháng xem Nhật ký 1 lần và thử phục hồi 1 bản sao lưu lên máy bạn.
