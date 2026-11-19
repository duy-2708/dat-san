#!/usr/bin/env bash
# Cập nhật bản mới từ GitHub lên máy chủ: sudo bash /var/www/dat-san/trien-khai/cap-nhat.sh
# Web chuyển sang trang "Đang bảo trì" ~30 giây, tự bật lại kể cả khi lỗi.
set -euo pipefail
cd "$(dirname "$0")/.."
export COMPOSER_ALLOW_SUPERUSER=1
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

php artisan sao-luu:csdl                       # sao lưu trước khi đổi CSDL
php artisan down --retry=15 --refresh=15 || true
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
chown -R www-data:www-data storage bootstrap/cache
php artisan queue:restart
systemctl reload php8.4-fpm
php artisan up
echo "== Kiem tra lo hong thu vien =="
composer audit --no-dev || echo "!! Co thu vien can cap nhat — bao Claude."
echo "== XONG =="
