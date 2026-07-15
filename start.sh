#!/usr/bin/env bash
#
# Menjalankan System AI Preneur (lokal, SQLite):
#   - Vite dev server  (asset React + Tailwind, mode watch)
#   - Laravel app      → http://127.0.0.1:8123
#
# Pakai:  ./start.sh          (jalankan app + vite)
#         ./start.sh stop     (hentikan app & vite)
#
set -e

export PATH="/opt/homebrew/opt/php/bin:$PATH"
APP_PORT=8123
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$PROJECT_DIR"

if [ "$1" = "stop" ]; then
    echo "Menghentikan app & vite..."
    pkill -f "php artisan serve --host=127.0.0.1 --port=$APP_PORT" 2>/dev/null || true
    pkill -f "vite" 2>/dev/null || true
    echo "Selesai."
    exit 0
fi

# 1. Pastikan file database SQLite ada
if [ ! -f database/database.sqlite ]; then
    echo "▶ Membuat database SQLite..."
    touch database/database.sqlite
    php artisan migrate --seed
fi
echo "✓ Database SQLite siap"

# 2. Vite dev server (asset watch) — jalan bila belum aktif
if ! pgrep -f "vite" >/dev/null 2>&1; then
    echo "▶ Menyalakan Vite dev server..."
    nohup npm run dev > storage/logs/vite.log 2>&1 &
    sleep 2
fi
echo "✓ Vite dev jalan (log: storage/logs/vite.log)"

# 3. Laravel app
if ! curl -s -o /dev/null "http://127.0.0.1:$APP_PORT" 2>/dev/null; then
    echo "▶ Menyalakan Laravel app (port $APP_PORT)..."
    nohup php artisan serve --host=127.0.0.1 --port=$APP_PORT > storage/logs/serve.log 2>&1 &
    sleep 2
fi
echo "✓ App → http://127.0.0.1:$APP_PORT"

echo ""
echo "Semua siap. Login: admin@example.com / password123"
