# System AI Preneur

Aplikasi manajemen bisnis untuk ekosistem **AI Preneur** — mengelola **pipeline endorsement**, **papan Kanban** produksi, **order**, **mindmap**, **pembukuan keuangan**, **script** harian, dan **manajemen user** berbasis peran. Menggantikan spreadsheet manual; pembayaran mendukung IDR & USD dengan konversi kurs otomatis.

Dokumen terkait: [PRD.md](docs/PRD.md) · [DESIGN.md](docs/DESIGN.md) · [SKILLS.md](docs/SKILLS.md) · [AGENTS.md](docs/AGENTS.md) · [CLAUDE.md](docs/CLAUDE.md)

---

## Modul

| Menu | Isi |
|------|-----|
| **Dashboard** | Ringkasan pipeline, kanban (per progress), & pembukuan (omzet IDR/USD/kurs). Filter periode per bulan (`?bulan=YYYY-MM`); grafik tren sengaja tetap menampilkan semua bulan. |
| **Sales** (Pipeline) | Tabel lengkap per board: account, endorse, output, progress, tanggal, payment, jumlah IDR/USD, kontak (WA/Gmail/DM IG), notes. Filter + CRUD via modal + report PDF. |
| **Kanban** | Papan bergaya Trello. Board & kolom **dinamis** (bukan enum tetap), drag-drop kartu & kolom, label warna. Detail fitur kartu di bawah. |
| **Order** | Pencatatan order & output produksi. |
| **Mindmap** | Papan mindmap (mind-elixir) dengan 5 kerangka siap pakai: Kosong, Brainstorm Konten, SWOT Brand, Rencana Kampanye, Alur Produksi. |
| **Script** | Naskah harian per brand (Raveloux, Rave Tailor, Freddie Kashawan). Diisi agen luar lewat `POST /api/scripts` — lihat [Integrasi agen Script](#integrasi-agen-script). |
| **Pembukuan** | Rekap keuangan Vue + **Chart.js**: pemasukan/pengeluaran per bulan, laba, inventaris, report PDF. |
| **User** | CRUD user + peran. |
| **Manajemen Akses** | Matriks peran × menu. Hak akses disimpan di tabel `role_menu_access`, bukan konstanta kode. |

### Fitur kartu Kanban

- **Deadline** — badge tanggal, merah bila lewat tenggat.
- **Arsip** — arsipkan kartu selesai; toggle "Arsip (n)" untuk melihat & mengembalikan.
- **Label** — preset warna (Urgent/Penting/Review/Selesai/Info); Urgent memberi penanda merah menonjol.
- **Deskripsi** — detail task per kartu.
- **Kontak** — WhatsApp, Gmail, DM Instagram.
- **Lampiran** — unggah/unduh/hapus file (disimpan di disk `public`, maks 10 MB).
- **Komentar** — thread diskusi; **staff pun boleh berkomentar** meski hanya bisa melihat.
- **Checklist** — todolist dengan progress bar.

### Hak akses per peran

| Peran | Menu | Kelola (CRUD kartu/board/kolom/lampiran) |
|-------|------|------------------------------------------|
| `owner` | semua | ✅ penuh |
| `it` | semua | ✅ penuh |
| `manager` | semua **kecuali** User | ✅ penuh |
| `admin` | Sales, Kanban, Mindmap | ✅ penuh (terbatas 3 menu itu) |
| `staff` | Kanban, Mindmap | ❌ lihat + **komentar** |

Otorisasi dua lapis: `EnsureMenuAccess` (akses menu) + cek `canManage()` untuk route mutasi. UI menyembunyikan aksi yang tak diizinkan; server tetap menolak dengan 403.

**Sumber kebenaran hak akses = tabel `role_menu_access`.** Kalau tabel itu belum ada (migrasi belum jalan) atau perannya belum punya baris, sistem jatuh ke `User::MENU_ACCESS` di kode — bukan membuka semua pintu.

**Pagar anti-kekunci owner (jangan dihapus).** Dua lapis: `AksesController` mengabaikan kiriman yang mengosongkan owner, dan `User::canSee()` memaksa owner selalu `true` walau barisnya diutak-atik langsung di DB. Tanpa itu, satu centang salah bisa membuat tak ada seorang pun yang masih bisa membuka halaman pengaturannya.

---

## Autentikasi

| Alur | Route | Catatan |
|------|-------|---------|
| Login | `/login` | |
| **Registrasi mandiri** | `/register` | **Terbuka.** Pendaftar langsung aktif sebagai `staff`. Peran dipatok di server, bukan dari input. |
| Lupa password | `/forgot-password` | Password broker bawaan Laravel: token ter-hash, kedaluwarsa 60 menit, sekali pakai. Jawaban untuk email tak terdaftar dibuat sama persis (anti-enumerasi). |
| Reset password | `/reset-password/{token}` | Nama route `password.reset` **wajib persis** — notifikasi bawaan Laravel membangun URL tautannya dari situ. |

> ⚠️ **Registrasi terbuka**: siapa pun yang tahu URL `/register` mendapat akun `staff` dan langsung bisa membaca Kanban + Mindmap. Ini pilihan sadar. Untuk menutupnya, tambahkan kolom status di `users` dan gerbangkan di `AuthController::register`.

Reset password butuh SMTP aktif di `.env` produksi, dan `APP_URL` **wajib** domain asli — tautan reset dibangun dari situ.

**Sesi habis** (`SESSION_LIFETIME`, default 120 menit) dilempar ke `/login` dengan pesan, bukan layar "Page Expired". Redirect-nya **303**, bukan 302: exception CSRF dilempar sebelum middleware Inertia, jadi konversi 302→303 bawaannya tak kebagian. Request JSON sengaja tetap menerima 419 — kalau ikut diredirect, `fetch()` resolve 200 dan kegagalan lolos diam-diam.

---

## Tech Stack

| Lapisan | Teknologi |
|---------|-----------|
| Backend | Laravel 13, PHP 8.5 |
| Frontend | **Inertia.js + Vue 3** (SPA, `<script setup>`), Tailwind CSS v4, Vite |
| Grafik | Chart.js via vue-chartjs (khusus modul Pembukuan) |
| Mindmap | mind-elixir |
| Database | **MySQL / MariaDB** (dev & produksi) |
| PDF | barryvdh/laravel-dompdf (report Sales & Pembukuan) |
| Kurs | open.er-api.com (gratis, tanpa API key; cache 12 jam, fallback Rp 16.000) |

> **Bukan** React, Livewire, Alpine. Tanpa queue, events, Policies, atau lapisan Actions/Services.
>
> Arsitektur SPA: setiap halaman adalah komponen **Vue** di `resources/js/Pages/`, dirender via `Inertia::render()` dari controller. Tidak ada Blade page (kecuali root `app.blade.php` & template PDF).

---

## Menjalankan (lokal)

```bash
# 1. Dependency (sekali saja)
composer install
npm install

# 2. Konfigurasi
cp .env.example .env        # jika .env belum ada
php artisan key:generate    # jika APP_KEY kosong

# 3. Database (MariaDB/MySQL) — buat skemanya dulu
mysql -u root -p -e "CREATE DATABASE pipeline CHARACTER SET utf8mb4;"
# lalu sesuaikan DB_* di .env

# 4. Migrasi + data contoh
php artisan migrate:fresh --seed

# 5. Build / dev asset
npm run dev                 # mode watch (development)
# atau: npm run build       # produksi (menghasilkan public/build/)

# 6. Jalankan server
php artisan serve
```

Login contoh: `admin@example.com` / `password123` (peran `owner`). Seeder membuat **5 user** — owner, dua manager (`rani@`, `dimas@`), dan it (`audi@`) — semuanya password sama.

### Verifikasi

```bash
php artisan test     # seluruh suite harus hijau
npm run build        # harus sukses
```

> `npm run build` **buta terhadap error dev-only.** Build hijau tidak membuktikan halaman hidup — komentar di dalam `<template #item>`, misalnya, hanya pecah di `npm run dev`. Untuk perubahan frontend, buka halamannya di browser.

---

## Deploy

Produksi = **shared hosting**. Server **tidak menjalankan Node**; aset di-build di laptop lalu diunggah.

1. **Build di laptop:** `npm run build` → menghasilkan `public/build/` (sengaja **tidak** di-`.gitignore`).
2. **Upload** kode + folder `public/build/`.
3. **Backup DB dulu:** `mysqldump -u USER -p NAMA_DB > ~/backup_$(date +%F).sql`
4. **Migrasi:** `php artisan migrate:status` untuk melihat yang tertunda, lalu `php artisan migrate --force`.
5. **`.env` produksi:** `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL` domain asli, koneksi DB server, SMTP, `SCRIPT_AGENT_TOKEN`.
6. **Server:** `composer install --no-dev --optimize-autoloader`, `php artisan storage:link` (agar lampiran kartu bisa diakses publik), lalu **`php artisan optimize:clear`**.

> **`optimize:clear` wajib setelah menyentuh `.env`.** Tanpa itu nilai lama masih di-cache dan gejalanya menyesatkan — kamu akan mengira nilai barunya yang salah.
>
> Cara cepat memastikan `.env` sudah mendarat: pancing satu error dan lihat apakah stack trace ikut terkirim. Kalau iya, `APP_DEBUG` masih `true` dan perubahan `.env` apa pun belum berlaku.

Mengubah tampilan/logic frontend → **build ulang** di laptop lalu upload `public/build/` yang baru.

---

## Integrasi agen Script

Naskah harian tidak digenerate di dalam aplikasi. Agen luar (VPS, lewat proxy LLM 9router) menyusunnya lalu menyetor via HTTP:

```
POST /api/scripts
Authorization: Bearer <SCRIPT_AGENT_TOKEN>

{ "brand": "raveloux", "generated_for": "2026-07-20",
  "scripts": [ { "title": "...", "body": "..." } ] }
```

- `brand` ∈ `raveloux` · `rave_tailor` · `fk`
- Route ini **di luar** `routes/web.php`: tanpa sesi/CSRF, tanpa `EnsureMenuAccess`. Gerbangnya token, dibandingkan dengan `hash_equals()` (waktu tetap). Throttle 30/menit.
- **Ganti-paket, bukan tambah**: kiriman menghapus lalu mengganti seluruh naskah untuk `brand` + `generated_for` yang sama. Aman diulang; **jangan** dites dengan tanggal yang berisi naskah asli.
- **503** = `SCRIPT_AGENT_TOKEN` belum diisi di server (tetap menolak — fail closed). **401** = token dikirim tapi tidak cocok. Dua kode berbeda ini disengaja agar kegagalan pemasangan bisa dibedakan tanpa akses tinker.

Token yang sama disimpan di dua tempat: `SCRIPT_AGENT_TOKEN` di `.env` hosting (yang memverifikasi) dan `APP_SCRIPT_TOKEN` di VPS (yang mengirim). GitHub Actions **tidak dipakai** — runner cloud tak bisa menjangkau 9router yang hidup di `127.0.0.1` VPS.

---

## Struktur Penting

```
app/Models/
  Pipeline.php                        # model utama + relasi (comments, attachments, outputs, assignee)
  BoardColumn.php  Category.php       # kolom & board dinamis kanban
  PipelineComment.php  PipelineAttachment.php
  Transaction.php  Inventory.php      # pembukuan
  Mindmap.php                         # papan mindmap + kerangka template
  Script.php                          # naskah harian per brand
  User.php                            # peran + canSee()/canManage()/homeRoute()
app/Http/Controllers/
  PipelineController.php              # pipeline + kanban + archive
  BoardController.php  ColumnController.php
  CommentController.php  AttachmentController.php
  DashboardController.php  PembukuanController.php  OrderController.php
  MindmapController.php  ScriptController.php
  UserController.php  AksesController.php
  AuthController.php  PasswordResetController.php
  Api/ScriptIngestController.php      # pintu agen Script (token, bukan sesi)
app/Http/Middleware/
  HandleInertiaRequests.php           # shared props (auth, flash)
  EnsureMenuAccess.php                # otorisasi menu + route mutasi
bootstrap/app.php                     # routing, middleware, penanganan 419 → login
resources/js/
  app.js                              # entry Inertia
  Layout.vue  Sidebar.vue  ModalWrap.vue   # kerangka + navigasi + modal
  Pages/                              # Login, Register, ForgotPassword, ResetPassword,
                                      # Dashboard, Kanban, Orders/, Mindmap/, Pembukuan,
                                      # Script, Users, Akses, BoardGallery
  scripts/components/                 # komponen Chart.js pembukuan
resources/css/app.css                 # Tailwind v4 + palet brand + safelist warna dinamis
resources/views/
  app.blade.php                       # root Inertia (satu-satunya blade halaman)
  pipelines/report.blade.php  pembukuan/report.blade.php   # template PDF
```

> **Warna dinamis dari DB** (kolom/label kanban) wajib didaftarkan di `resources/css/app.css` lewat `@source inline(...)` — scanner Tailwind tak membaca nilai yang datang dari database.

---

## Keamanan

- **JANGAN commit `.env`** (memuat `APP_KEY`, kredensial DB, SMTP, `SCRIPT_AGENT_TOKEN`). Sudah di `.gitignore`.
- **`APP_DEBUG=false` di produksi.** Kalau menyala, setiap error mengirim stack trace lengkap berisi path absolut server ke siapa pun, tanpa perlu login.
- **Registrasi `/register` terbuka** — lihat catatan di [Autentikasi](#autentikasi).
- Kredensial seeder (`admin@example.com` / `password123`) hanya untuk **lokal** — ganti di produksi.
- Data pipeline pada seeder bersifat **contoh**; anonimkan nilai sensitif sebelum repo dibuat publik.
- Lampiran diunggah ke `storage/app/public`; batas 10 MB per file.

### Menguji pagar keamanan

Tes hijau tidak membuktikan pagarnya hidup. Untuk logika otorisasi, **uji-mutasi**: lumpuhkan pagarnya, tes wajib merah. Kalau tetap hijau, tesnya vakum. Pastikan mutasinya tidak merusak sintaks — merah karena `ParseError` itu bukti palsu.
