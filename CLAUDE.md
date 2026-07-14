# CLAUDE.md — panduan cepat untuk Claude Code di repo ini

System AI Preneur — aplikasi manajemen bisnis (Dashboard, Pipeline, Kanban, Pembukuan, Script, User) dengan hak akses per peran.

Konvensi lengkap ada di **[AGENTS.md](AGENTS.md)** · arsitektur di **[DESIGN.md](DESIGN.md)**. File ini ringkasan operasional.

## Stack
- **Laravel 13 / PHP 8.5**, **SQLite (WAL)** untuk dev.
- **Inertia.js + Vue 3** (SPA) · Tailwind v4 · Vite · **Chart.js via vue-chartjs** (Pembukuan).
- **BUKAN**: React, Livewire, Alpine, queue, events, Policies, Actions/Services layer.
- Produksi = **shared hosting**: build aset di laptop (`npm run build`), upload `public/build/` — server tak jalankan Node.

## Struktur
- Controller → `Inertia::render('Page', $props)`. PDF report = DomPDF (blade di `resources/views/*/report.blade.php`). Drag/todo = endpoint JSON.
- Halaman = **Vue SFC** `<script setup>` di `resources/js/Pages/*.vue`. Kerangka: `Layout.vue`, `Sidebar.vue`, `ModalWrap.vue`. Entry: `resources/js/app.js`.
- Otorisasi: middleware `EnsureMenuAccess` (akses menu) + `User::canManage()` (route mutasi). **Jangan** Policy per-model / cek role manual.

## Aturan penting
- **Inertia Vue `useForm`**: field **top-level** (`form.endorse`, `v-model="form.endorse"`), bukan `form.data.x`. Submit `form.post/put`, error `form.errors.x`, upload `forceFormData: true`.
- **Warna dinamis dari DB** (kolom/label kanban) wajib di safelist `resources/css/app.css` (`@source inline(...)`) — scanner Tailwind tak membacanya.
- **Komentar kode**: Bahasa Indonesia, rinci (logika `<script>` + tiap seksi `<template>`).
- `progress`/`category` = string dinamis (dari `board_columns`/`categories`), bukan enum.
- Komentar kartu boleh untuk `editor`/`staff` (view-only); arsip/lampiran/CRUD hanya `super_admin`/`it`.

## Jalankan
```bash
composer install && npm install
touch database/database.sqlite && php artisan migrate:fresh --seed
npm run dev            # watch  (atau: npm run build)
php artisan serve      # login: admin@example.com / password123
```
Setelah ubah frontend: `npm run build`. Deploy butuh `php artisan storage:link` (lampiran).

## Verifikasi
- `npm run build` harus sukses.
- Smoke test HTTP per peran: super_admin (semua 200 + aksi), editor/staff (Kanban 200, komentar OK, arsip/lampiran → 403).
