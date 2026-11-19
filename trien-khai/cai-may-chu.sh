#!/usr/bin/env bash
# =====================================================================
#  CÀI MÁY CHỦ CHO ĐẶT SÂN — Ubuntu 22.04 / 24.04, chạy bằng root.
#  Cách dùng (xem docs/02-trien-khai-may-chu.md):
#     git clone https://github.com/duy-2708/dat-san.git /var/www/dat-san
#     sudo bash /var/www/dat-san/trien-khai/cai-may-chu.sh datsan.vn email-cua-ban@gmail.com
#  Chạy lại nhiều lần vẫn an toàn (không xoá dữ liệu, không đổi mật khẩu CSDL đã có).
# =====================================================================
set -euo pipefail

TEN_MIEN="${1:?Thieu ten mien. VD: sudo bash cai-may-chu.sh datsan.vn ban@gmail.com}"
EMAIL="${2:?Thieu email nhan canh bao chung chi SSL}"
THU_MUC="$(cd "$(dirname "$0")/.." && pwd)"
PHP=8.4
PHP_SOCK="/run/php/php${PHP}-fpm.sock"
export DEBIAN_FRONTEND=noninteractive COMPOSER_ALLOW_SUPERUSER=1

[ "$(id -u)" -eq 0 ] || { echo "Hay chay bang sudo"; exit 1; }
buoc() { echo; echo "=== $* ==="; }

buoc "1. Cap nhat he dieu hanh + cai goi"
apt-get update -y && apt-get upgrade -y
apt-get install -y software-properties-common curl git unzip ufw fail2ban unattended-upgrades \
    nginx mariadb-server supervisor certbot python3-certbot-nginx rclone
add-apt-repository -y ppa:ondrej/php
apt-get update -y
apt-get install -y php$PHP-fpm php$PHP-cli php$PHP-mysql php$PHP-mbstring php$PHP-xml php$PHP-curl \
    php$PHP-zip php$PHP-gd php$PHP-intl php$PHP-bcmath php$PHP-opcache
command -v composer >/dev/null || curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

buoc "2. Tu cap nhat ban va bao mat + bo nho ao"
dpkg-reconfigure -f noninteractive unattended-upgrades
if ! swapon --show | grep -q .; then
    fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

buoc "3. Tuong lua + fail2ban"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
cp "$THU_MUC/trien-khai/fail2ban-jail.local" /etc/fail2ban/jail.local
systemctl enable --now fail2ban && systemctl restart fail2ban

buoc "4. PHP an toan hon"
INI=/etc/php/$PHP/fpm/php.ini
sed -i 's/^;*expose_php.*/expose_php = Off/; s/^;*upload_max_filesize.*/upload_max_filesize = 8M/; s/^;*post_max_size.*/post_max_size = 40M/' "$INI"
sed -i 's/^;*opcache.enable=.*/opcache.enable=1/; s/^;*opcache.memory_consumption=.*/opcache.memory_consumption=128/' "$INI"
systemctl restart php$PHP-fpm

buoc "5. Co so du lieu"
systemctl enable --now mariadb
cd "$THU_MUC"
if [ -f .env ]; then
    MK_DB="$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"')"
else
    MK_DB="$(openssl rand -base64 32 | tr -dc 'A-Za-z0-9' | head -c 28)"
fi
mariadb <<SQL
CREATE DATABASE IF NOT EXISTS dat_san CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'dat_san'@'localhost' IDENTIFIED BY '${MK_DB}';
ALTER USER 'dat_san'@'localhost' IDENTIFIED BY '${MK_DB}';
GRANT ALL PRIVILEGES ON dat_san.* TO 'dat_san'@'localhost';
DELETE FROM mysql.user WHERE User='';
DROP DATABASE IF EXISTS test;
FLUSH PRIVILEGES;
SQL

buoc "6. Ma nguon + .env"
if [ ! -f .env ]; then
    cp trien-khai/env.production.mau .env
    sed -i "s#^APP_URL=.*#APP_URL=https://${TEN_MIEN}#; s#^DB_PASSWORD=.*#DB_PASSWORD=${MK_DB}#" .env
    MOI=1
fi
chmod 640 .env
composer install --no-dev --optimize-autoloader --no-interaction
grep -qE '^APP_KEY=base64' .env || php artisan key:generate --force
php artisan migrate --force
if [ "${MOI:-0}" = 1 ]; then php artisan db:seed --force; fi   # production: chi du lieu nen, khong tai khoan mau
php artisan storage:link || true
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache   # sau cung: file do root vua tao cung thuoc www-data
chown root:www-data .env
find storage bootstrap/cache -type d -exec chmod 775 {} \;

buoc "7. Nginx (IP that qua Cloudflare)"
{
    echo "# Tu sinh boi cai-may-chu.sh — dai IP Cloudflare"
    for u in https://www.cloudflare.com/ips-v4 https://www.cloudflare.com/ips-v6; do
        curl -fsS --max-time 10 "$u" | sed 's/^/set_real_ip_from /; s/$/;/' || true
    done
    echo "real_ip_header CF-Connecting-IP;"
} > /etc/nginx/cloudflare-ip.conf
sed -e "s#TEN_MIEN#${TEN_MIEN}#g; s#THU_MUC#${THU_MUC}#g; s#PHP_SOCK#${PHP_SOCK}#g" trien-khai/nginx-dat-san.conf > /etc/nginx/sites-available/dat-san
ln -sf /etc/nginx/sites-available/dat-san /etc/nginx/sites-enabled/dat-san
rm -f /etc/nginx/sites-enabled/default
sed -i 's/# server_tokens off;/server_tokens off;/' /etc/nginx/nginx.conf
nginx -t && systemctl reload nginx

buoc "8. Hang doi email + lich chay dinh ky"
sed "s#THU_MUC#${THU_MUC}#g" trien-khai/supervisor-dat-san.conf > /etc/supervisor/conf.d/dat-san.conf
supervisorctl reread && supervisorctl update && supervisorctl restart 'dat-san-hang-doi:*' || true
echo "* * * * * www-data cd ${THU_MUC} && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/dat-san
chmod 644 /etc/cron.d/dat-san

buoc "9. Chung chi HTTPS (Let's Encrypt, mien phi, tu gia han)"
if certbot --nginx -d "$TEN_MIEN" -d "www.$TEN_MIEN" --non-interactive --agree-tos -m "$EMAIL" --redirect; then
    echo "Da bat HTTPS."
else
    echo "!! Chua cap duoc chung chi: kiem tra ten mien da tro ve IP may chu chua (Cloudflare de 'DNS only' - may xam), roi chay lai script."
fi

echo
echo "=================== XONG ==================="
echo " Web: https://${TEN_MIEN}"
echo " Viec con lai (xem docs/02-trien-khai-may-chu.md):"
echo "   1) nano ${THU_MUC}/.env  -> dien MAIL_*, GOOGLE_*  roi chay: sudo -u www-data php artisan optimize"
echo "   2) sudo -u www-data php artisan tao-admin email-cua-ban@gmail.com   -> roi dang nhap, bat xac minh 2 buoc"
echo "   3) Dang ky UptimeRobot theo doi https://${TEN_MIEN}/up"
echo "   4) (Nen) cau hinh rclone sao luu len Google Drive"
