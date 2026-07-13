# System AI Preneur

Aplikasi manajemen bisnis untuk ekosistem **AI Preneur** — mengelola **pipeline endorsement**, **papan Kanban** produksi, **pembukuan keuangan**, template **script**, dan **manajemen user** berbasis peran. Menggantikan spreadsheet manual; pembayaran mendukung IDR & USD dengan konversi kurs otomatis.

Dokumen terkait: [PRD.md](PRD.md) · [DESIGN.md](DESIGN.md) · [SKILLS.md](SKILLS.md) · [AGENTS.md](AGENTS.md)

---

## Modul

| Menu | Isi |
|------|-----|
| **Dashboard** | Ringkasan pipeline, kanban (per progress), & pembukuan (omzet IDR/USD/kurs). |
| **Pipeline** | Tabel lengkap per board: account, endorse, output, progress, tanggal, payment, jumlah IDR/USD, notes, dll. Filter + CRUD via modal + report PDF. |
| **Kanban** | Papan bergaya Trello. Board & kolom **dinamis** (bukan enum tetap), drag-drop kartu, label warna. Lihat detail fitur kartu di bawah. |
| **Script** | Grid folder naskah (template; data dikirim Hermes agent ke `public/scripts/*`). |
| **Pembukuan** | Rekap keuangan React + **Chart.js**: pemasukan/pengeluaran per bulan, laba, inventaris, report PDF. |
| **User** | CRUD user + peran (super_admin, it, admin, editor, staff). |

### Fitur kartu Kanban

- **Deadline** — badge tanggal, merah bila lewat tenggat.
- **Arsip** — arsipkan kartu selesai; toggle "Arsip (n)" untuk melihat & mengembalikan.
- **Label** — preset warna (Urgent/Penting/Review/Selesai/Info); Urgent memberi penanda merah menonjol.
- **Deskripsi** — detail task per kartu.
- **Lampiran** — unggah/unduh/hapus file (disimpan di disk `public`).
- **Komentar** — thread diskusi; **staff yang ditugasi pun boleh berkomentar** meski hanya bisa melihat.
- **Checklist** — todolist dengan progress bar.

### Hak akses per peran

| Peran | Menu | Kelola (CRUD kartu/board/kolom/lampiran) |
|-------|------|------------------------------------------|
| `super_admin`, `it` | semua | ✅ penuh |
| `admin` | Script, Kanban | ❌ lihat saja |
| `editor` | Kanban | ❌ lihat + **komentar** |
| `staff` | Kanban | ❌ lihat + **komentar** |

Otorisasi dua lapis: `EnsureMenuAccess` (akses menu) + cek `canManage()` untuk route mutasi. UI menyembunyikan aksi yang tak diizinkan; server tetap menolak dengan 403.

---

## Tech Stack

| Lapisan | Teknologi |
|---------|-----------|
| Backend | Laravel 13, PHP 8.5 |
| Frontend | **Inertia.js + React 19** (SPA), Tailwind CSS v4, Vite |
| Grafik | Chart.js (khusus modul Pembukuan) |
| Database | **SQLite** (mode WAL) |
| PDF | barryvdh/laravel-dompdf (report Pipeline & Pembukuan) |
| Kurs | open.er-api.com (gratis, tanpa API key; cache 12 jam, fallback Rp 16.000) |

> Arsitektur SPA: setiap halaman adalah komponen React di `resources/js/Pages/`, dirender via `Inertia::render()` dari controller. Tidak ada Blade page (kecuali root `app.blade.php` & template PDF).

---

## Menjalankan (lokal)

```bash
# 1. Dependency (sekali saja)
composer install
npm install

# 2. Konfigurasi
cp .env.example .env        # jika .env belum ada
php artisan key:generate    # jika APP_KEY kosong
touch database/database.sqlite   # buat file DB SQLite (jika belum ada)

# 3. Migrasi + data contoh
php artisan migrate:fresh --seed

# 4. Build / dev asset
npm run dev                 # mode watch (development)
# atau: npm run build       # produksi (menghasilkan public/build/)

# 5. Jalankan server
php artisan serve
```

Login contoh: `admin@example.com` / `password123` (super_admin). Seeder juga membuat editor/staff (`dimas@example.com`, `rani@example.com`, dll) dengan password sama.

Data contoh: 21 pipeline, 5 board (kategori), 21 transaksi pembukuan, 7 user.

---

## Deploy

Server **tidak menjalankan Node**. Tailwind & React di-build di laptop; hasilnya file statis di `public/build/` yang ikut diunggah.

1. **Build di laptop:** `npm run build` → menghasilkan `public/build/`.
2. **Upload** kode + folder `public/build/` (folder ini sengaja tidak di-`.gitignore`).
3. **Database:** import file `.sql` (schema + data) — dump disiapkan di folder `/wrapxbake/sql`. Sesuaikan dialek dengan DB target server.
4. **`.env` produksi:** `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` (`php artisan key:generate`), koneksi DB server.
5. **Server:** `composer install --no-dev --optimize-autoloader` lalu `php artisan storage:link` (agar lampiran kartu bisa diakses publik).

> Mengubah tampilan/logic frontend → **build ulang** di laptop lalu upload `public/build/` yang baru.

---

## Struktur Penting

```
app/Models/
  Pipeline.php                       # model utama + relasi (comments, attachments, outputs, assignee)
  BoardColumn.php  Category.php       # kolom & board dinamis kanban
  PipelineComment.php  PipelineAttachment.php
  Transaction.php  Inventory.php      # pembukuan
  User.php                            # peran + canSee()/canManage()/homeRoute()
app/Http/Controllers/
  PipelineController.php              # pipeline + kanban + archive
  BoardController.php  ColumnController.php
  CommentController.php  AttachmentController.php
  DashboardController.php  PembukuanController.php  UserController.php  AuthController.php
app/Http/Middleware/
  HandleInertiaRequests.php           # shared props (auth, flash)
  EnsureMenuAccess.php                # otorisasi menu + route mutasi
resources/js/
  app.jsx                            # entry Inertia
  Layout.jsx  Sidebar.jsx            # kerangka + navigasi
  Pages/                             # Login, Dashboard, Kanban, Pipelines/Index, Pembukuan, Script, Users
  scripts/components/                # komponen Chart.js pembukuan
resources/css/app.css                # Tailwind v4 + palet brand + safelist warna dinamis
resources/views/
  app.blade.php                      # root Inertia (satu-satunya blade halaman)
  pipelines/report.blade.php  pembukuan/report.blade.php   # template PDF
```

---

## Keamanan

- **JANGAN commit `.env`** (memuat `APP_KEY` & kredensial). Sudah di `.gitignore`.
- Kredensial seeder (`admin@example.com` / `password123`) hanya untuk **lokal** — ganti di produksi.
- Data pipeline pada seeder bersifat **contoh**; anonimkan nilai sensitif sebelum repo dibuat publik.
- Lampiran diunggah ke `storage/app/public`; batas 10 MB per file.
