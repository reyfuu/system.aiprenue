#!/usr/bin/env bash
#
# Menjalankan semua layanan Pipeline FK-AI Preneur:
#   - MariaDB (database)
#   - Laravel app   → http://127.0.0.1:8123
#   - phpMyAdmin    → http://127.0.0.1:8081
#
# Pakai:  ./start.sh          (jalankan semua)
#         ./start.sh stop     (hentikan app & phpMyAdmin)
#
set -e

export PATH="/opt/homebrew/opt/php/bin:/opt/homebrew/opt/mariadb/bin:$PATH"
APP_PORT=8123
PMA_PORT=8081
PMA_DIR=/opt/homebrew/share/phpmyadmin
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"

if [ "$1" = "stop" ]; then
    echo "Menghentikan app & phpMyAdmin..."
    pkill -f "php artisan serve --host=127.0.0.1 --port=$APP_PORT" 2>/dev/null || true
    pkill -f "php -S 127.0.0.1:$PMA_PORT" 2>/dev/null || true
    echo "Selesai. (MariaDB dibiarkan tetap jalan)"
    exit 0
fi

# 1. MariaDB
if ! mysqladmin ping -h 127.0.0.1 --silent 2>/dev/null; then
    echo "▶ Menyalakan MariaDB..."
    brew services start mariadb >/dev/null
    until mysqladmin ping -h 127.0.0.1 --silent 2>/dev/null; do sleep 1; done
fi
echo "✓ MariaDB jalan"

# 2. Laravel app
if ! curl -s -o /dev/null "http://127.0.0.1:$APP_PORT" 2>/dev/null; then
    echo "▶ Menyalakan Laravel app (port $APP_PORT)..."
    cd "$PROJECT_DIR"
    nohup php artisan serve --host=127.0.0.1 --port=$APP_PORT > storage/logs/serve.log 2>&1 &
    sleep 2
fi
echo "✓ App     → http://127.0.0.1:$APP_PORT"

# 3. phpMyAdmin
if ! curl -s -o /dev/null "http://127.0.0.1:$PMA_PORT/index.php" 2>/dev/null; then
    echo "▶ Menyalakan phpMyAdmin (port $PMA_PORT)..."
    nohup php -S 127.0.0.1:$PMA_PORT -t "$PMA_DIR" > "$PROJECT_DIR/storage/logs/pma.log" 2>&1 &
    sleep 1
fi
echo "✓ phpMyAdmin → http://127.0.0.1:$PMA_PORT   (user: root, password: kosong)"

echo ""
echo "Semua siap. Login app: admin@example.com / password123"
