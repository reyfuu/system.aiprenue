# Pipeline FK-AI Preneur

Aplikasi web manajemen **pipeline endorsement, agensi, coaching & speaker** untuk FK-AI Preneur — menggantikan spreadsheet "PIPELINE FK-AI PRENEUR". Mengelola progress produksi, pembayaran (IDR & USD), dan menyajikan report omzet dengan konversi kurs otomatis.

Dokumen terkait: [PRD.md](PRD.md) · [DESIGN.md](DESIGN.md) · [SKILLS.md](SKILLS.md) · [AGENTS.md](AGENTS.md)

---

## Fitur

- **4 kategori** dalam bentuk tab: Endorse, Agensi, Coaching, Speaker.
- **Tabel pipeline** lengkap (kolom A–N sheet): Account, Endorse, Output, Progress, Tanggal Posting/Payment, Status Payment, Jumlah IDR & USD, Notes, Ke Gilang, Catatan.
- **CRUD** via modal dengan **dialog konfirmasi** (tambah/edit/hapus) & notifikasi toast.
- **Status berwarna**: Progress (Script/Editing/Progress/Done/Pending/Tentatif), Payment (Lunas/DP/Belum), Ke Gilang (DONE/Sudah/Belum).
- **Warna account**: FK = biru, AI Preneur = violet.
- **Filter otomatis** (tanpa tombol) berdasarkan account, progress, payment, output, dan pencarian.
- **Dashboard omzet**: total IDR, USD, dan total gabungan (USD dikonversi ke IDR pakai **kurs terkini** — di-cache 12 jam, fallback Rp 16.000).
- **Report PDF** omzet per kategori / semua (landscape A4).
- **Login** (session auth) — semua halaman pipeline dilindungi.

---

## Tech Stack

| Lapisan | Teknologi |
|---------|-----------|
| Backend | Laravel 11, PHP 8.5 |
| Frontend | Blade + Tailwind CSS v4 + Alpine.js (di-**build** via Vite, bukan CDN) |
| Database | MariaDB |
| PDF | barryvdh/laravel-dompdf |
| Kurs | open.er-api.com (gratis, tanpa API key) |
| Admin DB | phpMyAdmin |

---

## Prasyarat (macOS + Homebrew)

```bash
brew install php composer mariadb phpmyadmin node
```

---

## Menjalankan (cara cepat)

```bash
./start.sh
```

Script ini menyalakan **MariaDB + Laravel app + phpMyAdmin** sekaligus.
Untuk menghentikan app & phpMyAdmin: `./start.sh stop`.

| Layanan | URL | Login |
|---------|-----|-------|
| **Aplikasi** | http://127.0.0.1:8123 | `admin@example.com` / `password123` |
| **phpMyAdmin** | http://127.0.0.1:8081 | user `root`, password **kosong** |

> Catatan: phpMyAdmin dipindah ke **port 8081** karena port 8080 sering dipakai proses lain.

---

## Menjalankan (manual, langkah demi langkah)

```bash
export PATH="/opt/homebrew/opt/php/bin:/opt/homebrew/opt/mariadb/bin:$PATH"

# 1. Database
brew services start mariadb

# 2. Dependency (sekali saja)
composer install
npm install

# 3. Konfigurasi
cp .env.example .env      # (jika .env belum ada)
php artisan key:generate  # (jika APP_KEY kosong)

# 4. Migrasi + data awal (dari sheet)
php artisan migrate:fresh --seed

# 5. Build asset frontend (Tailwind + Alpine)
npm run build             # atau `npm run dev` untuk mode watch

# 6. Jalankan
php artisan serve --port=8123
php -S 127.0.0.1:8081 -t /opt/homebrew/share/phpmyadmin
```

---

## Database

- **Nama DB**: `pipeline`
- **Koneksi** (`.env`): `mariadb`, host `127.0.0.1`, port `3306`, user `root`, password kosong.
- Seed berisi **46 baris** dari sheet (38 endorse, 6 agensi, 2 coaching) + akun admin.

Konfigurasi phpMyAdmin ada di `/opt/homebrew/etc/phpmyadmin.config.inc.php` (auth cookie, host `127.0.0.1`, izinkan password kosong).

---

## Troubleshooting

**phpMyAdmin tidak jalan / "This site can't be reached"**
Server `php -S` dev tidak berjalan permanen — akan mati bila terminal ditutup atau komputer restart. Jalankan lagi:
```bash
./start.sh
```
Jika port 8081 juga dipakai, ganti port: `php -S 127.0.0.1:8082 -t /opt/homebrew/share/phpmyadmin`.

**"Access denied for user 'root'"**
MariaDB root memakai password kosong via TCP. Pastikan `.env` `DB_PASSWORD=` (kosong).

**Tampilan berantakan / warna hilang**
Asset perlu di-build ulang setelah mengubah kelas Tailwind:
```bash
npm run build
```

**Cek apakah layanan hidup**
```bash
mysqladmin ping -h 127.0.0.1        # MariaDB
curl -I http://127.0.0.1:8123       # App
curl -I http://127.0.0.1:8081       # phpMyAdmin
```

---

## Keamanan (WAJIB dibaca sebelum repo dibuat public)

- **JANGAN commit `.env`** — sudah masuk `.gitignore`. File ini memuat `APP_KEY` & kredensial DB.
- **Kredensial admin di seeder** (`admin@example.com` / `password123`) hanya untuk **data contoh lokal**. Ganti password segera di lingkungan nyata, atau pindahkan ke variabel `.env` (`ADMIN_PASSWORD`).
- **Password DB root kosong** hanya aman untuk **lokal**. Untuk server, set password & user khusus.
- Data pipeline pada seeder bersifat **contoh**; hapus/anonimkan nilai sensitif (nominal, nama klien) sebelum repo publik jika tidak ingin dibagikan.

---

## Struktur Penting

```
app/Models/Pipeline.php              # model utama + konstanta enum & warna
app/Http/Controllers/
  PipelineController.php             # CRUD, filter, report PDF
  AuthController.php                 # login / logout
app/Support/ExchangeRate.php        # kurs USD→IDR (cache 12 jam)
database/seeders/PipelineSeeder.php # data dari sheet
resources/views/
  pipelines/index.blade.php         # dashboard tabel
  pipelines/report.blade.php        # template PDF omzet
  auth/login.blade.php              # halaman login
resources/css/app.css               # Tailwind v4 + palet brand
resources/js/app.js                 # Alpine.js
start.sh                            # start semua layanan
```
