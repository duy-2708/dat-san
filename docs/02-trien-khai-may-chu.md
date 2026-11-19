# Đưa web lên máy chủ thật (~20 sân)

Mục tiêu: chạy ổn định cho khoảng 20 sân với chi phí thấp nhất, cấu hình theo chuẩn thương mại để sau này mở rộng không phải làm lại.

## 0. Cần chuẩn bị

| Thứ | Chi phí | Ghi chú |
|---|---|---|
| VPS Ubuntu 24.04, **2 GB RAM**, 1–2 CPU, 20+ GB SSD | khoảng 100–150k/tháng (nhà cung cấp VN) | Đặt máy ở VN cho nhanh. Đủ cho ~20 sân |
| Tên miền (.com hoặc .vn) | .com khoảng 250–350k/năm | Mua ở nhà cung cấp bất kỳ, giá thay đổi theo đợt khuyến mãi |
| Cloudflare | **Miễn phí** | DNS, chống DDoS, ẩn IP máy chủ |
| Let's Encrypt (HTTPS) | **Miễn phí** | Script tự cài, tự gia hạn |
| UptimeRobot | **Miễn phí** | Báo email khi web sập |
| Google Drive (sao lưu ngoài) | **Miễn phí** 15 GB | Qua rclone |
| Gmail gửi mail | **Miễn phí** ~500 thư/ngày | Đủ cho 20 sân; đông hơn thì chuyển dịch vụ gửi mail |

> Oracle Cloud "Always Free" cũng chạy được (miễn phí, cấu hình mạnh) nhưng cần thẻ Visa để xác minh và hay hết máy ở khu vực Singapore — chỉ nên thử nếu muốn 0 đồng.

## 1. Trỏ tên miền qua Cloudflare
1. Tạo tài khoản cloudflare.com → **Add a site** → nhập tên miền → gói **Free**.
2. Cloudflare đưa 2 nameserver → vào trang nhà cung cấp tên miền, thay nameserver bằng 2 cái đó (chờ 5 phút – vài giờ).
3. **DNS** → thêm 2 bản ghi `A`: `@` và `www` → IP VPS. **Tạm để mây xám (DNS only)** để cài HTTPS.

## 2. Cài máy chủ (1 lệnh)
Đăng nhập VPS (Windows: mở **PowerShell** → `ssh root@IP-VPS`), rồi:
```bash
git clone https://github.com/duy-2708/dat-san.git /var/www/dat-san
sudo bash /var/www/dat-san/trien-khai/cai-may-chu.sh ten-mien-cua-ban.com email-cua-ban@gmail.com
```
Repo để riêng tư (private) thì GitHub hỏi mật khẩu → dùng **Personal access token** (GitHub → Settings → Developer settings → Tokens), không phải mật khẩu GitHub.

Script tự làm (~10 phút): cập nhật Ubuntu, bật tự vá bảo mật, tường lửa (chỉ mở SSH + web), fail2ban (khoá IP dò SSH), PHP 8.4, MariaDB (tự tạo mật khẩu ngẫu nhiên), Nginx (chặn file ẩn `.env`/`.git`, giới hạn 20 request/giây/IP), HTTPS, hàng đợi email chạy nền, lịch chạy định kỳ (nhả chỗ, nhắc lịch, quét kèo, **sao lưu 02:30**).

## 3. Việc còn lại sau khi cài
1. Điền mail & Google:
   ```bash
   nano /var/www/dat-san/.env          # chép MAIL_*, GOOGLE_* từ .env trên máy bạn
   cd /var/www/dat-san && sudo -u www-data php artisan optimize
   ```
2. Tạo admin thật rồi **bật xác minh 2 bước** (bắt buộc):
   ```bash
   sudo -u www-data php artisan tao-admin email-cua-ban@gmail.com
   ```
3. Google Cloud Console → OAuth client → thêm Redirect URI `https://ten-mien/auth/google/callback`.
4. Cloudflare: bật **mây cam (Proxied)** cho `@` và `www`; **SSL/TLS → Full (strict)**; **Security → Bot Fight Mode: On**. Bị tấn công thì bật **Under Attack Mode**.
5. UptimeRobot (uptimerobot.com) → **Add monitor** → HTTP(s) → `https://ten-mien/up` → 5 phút/lần → báo về email.
6. Admin → **Cài đặt**: đặt ngày miễn phí, tài khoản nhận tiền thuê bao.

## 4. Sao lưu ra Google Drive (nên làm)
Trên VPS: `rclone config` → `n` (new) → tên `gdrive` → loại `drive` → Enter các câu hỏi → **Use auto config? `n`**.
Rclone in ra 1 lệnh `rclone authorize "drive" "..."` → chạy lệnh đó trên **máy Windows** (tải rclone.org/downloads) → đăng nhập Google → dán mã trả về vào VPS.
Rồi bật lịch chép mỗi đêm:
```bash
cat > /etc/cron.d/dat-san-rclone <<'CRON'
50 2 * * * root rclone copy /var/www/dat-san/storage/app/sao-luu gdrive:dat-san-sao-luu --max-age 48h
10 3 * * 0 root rclone delete gdrive:dat-san-sao-luu --min-age 60d
CRON
```

## 5. Cập nhật bản mới
Sau khi push code mới lên GitHub:
```bash
sudo bash /var/www/dat-san/trien-khai/cap-nhat.sh
```
Tự: sao lưu → bật trang bảo trì → kéo code → cập nhật CSDL → xoá cache → khởi động lại hàng đợi → mở lại web → kiểm tra lỗ hổng thư viện.

## 6. (Nên) Đăng nhập SSH bằng khoá thay mật khẩu
Trên Windows PowerShell: `ssh-keygen` (Enter hết) → `type $env:USERPROFILE\.ssh\id_ed25519.pub | ssh root@IP "cat >> ~/.ssh/authorized_keys"`.
Thử `ssh root@IP` vào được **không cần mật khẩu** rồi mới tắt đăng nhập mật khẩu:
```bash
sed -i 's/^#*PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config && systemctl restart ssh
```

## 7. Danh sách kiểm tra trước khi mở cho sân thật
- [ ] `https://ten-mien` có ổ khoá, `http://` tự chuyển sang `https://`
- [ ] Mở `https://ten-mien/.env` → phải 404/403
- [ ] Gõ sai 1 đường dẫn → trang 404 tiếng Việt (không hiện chữ Laravel/đường dẫn file)
- [ ] Đăng ký → nhận mail xác thực; đặt sân → chủ sân nhận mail
- [ ] Admin đã bật 2 bước; không còn tài khoản `@datsan.test`
- [ ] `ls storage/app/sao-luu` có file sau 02:30; Google Drive có bản sao
- [ ] UptimeRobot báo "Up"
- [ ] Đã có trang Điều khoản & Chính sách bảo mật (khi đăng ký kinh doanh — xem mục 8)

## 8. Khi chạy thương mại chính thức (làm sau)
- Đăng ký hộ kinh doanh / doanh nghiệp; thông báo website TMĐT với Bộ Công Thương (online.gov.vn).
- Trang **Điều khoản sử dụng** + **Chính sách bảo vệ dữ liệu cá nhân** (Nghị định 13/2023).
- PayOS thật để tự xác nhận tiền vào.
- Vượt ~50 sân: nâng VPS 4 GB, chuyển cache/hàng đợi sang Redis (chỉ sửa `.env`).
