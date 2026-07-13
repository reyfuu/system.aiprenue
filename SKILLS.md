# SKILLS — Kapabilitas Sistem & Tim

Dokumen ini merinci kapabilitas (skill) yang dibutuhkan untuk **membangun** dan **mengoperasikan** Sistem Manajemen Proyek/Task berbasis Laravel + Tailwind.

Referensi arsitektur: [DESIGN.md](DESIGN.md) · Peran pembangun: [AGENTS.md](AGENTS.md)

---

## A. Skill Teknis (untuk membangun sistem)

### 1. Backend — Laravel / PHP
- **PHP 8.3+**: tipe data, enum, match expression, readonly property, attribute (`#[Fillable]`).
- **Eloquent ORM**: relasi (hasMany, belongsToMany, morphMany), eager loading, scope, accessor/mutator.
- **Migrasi & Seeder**: skema DB, foreign key, index, factory untuk data dummy.
- **Autentikasi & Otorisasi**: Laravel Breeze/Fortify, Policies, Gates, middleware.
- **Validasi**: Form Request, custom rules.
- **Queue & Jobs**: DB queue, job async untuk pekerjaan berat.
- **Events & Listeners**: pola event-driven untuk log aktivitas & notifikasi.
- **Testing**: PHPUnit/Pest — Feature test & Unit test.

### 2. Frontend — React + Chart.js + Tailwind
- **React**: komponen fungsional, hooks (`useState`/`useEffect`), props, mount ke Blade shell via Vite.
- **Chart.js**: grafik bar/line/pie untuk pembukuan & dashboard (omzet per bulan, tren, komposisi). Pakai `react-chartjs-2` sebagai wrapper.
- **Tailwind v4**: utility classes, responsive design, komponen reusable.
- **Vite**: bundling React + CSS, `@vite` entry di Blade.
- **Blade shell**: layout/entry yang me-mount komponen React; Alpine hanya untuk sisa interaksi ringan yang belum dimigrasi. (tanpa Livewire)
- **UX dasar**: form yang jelas, loading state, empty state, feedback aksi.

### 3. Database & Infrastruktur
- **SQL** (SQLite lokal / MySQL produksi): query, join, index, normalisasi.
- **Cache/session/queue**: driver database (tanpa Redis).
- **Git**: branching, PR, commit yang rapi.
- **Deployment**: `.env` config, migrasi produksi, queue worker (Supervisor), scheduler (cron).

---

## B. Skill Fungsional (kapabilitas yang disediakan aplikasi ke pengguna)

Kapabilitas yang dimiliki pengguna sesuai perannya (lihat matriks RBAC di DESIGN.md §6).

### Admin
- Kelola akun pengguna & role global.
- Konfigurasi sistem, akses semua proyek.
- Audit log & pemulihan data (soft delete).

### Manager / Project Lead
- Buat & kelola proyek beserta anggotanya.
- Susun task, tetapkan assignee, prioritas, dan deadline.
- Pantau progress lewat board kanban & dashboard.
- Terima laporan beban kerja dan burndown.

### Member
- Lihat proyek yang diikuti.
- Buat/ubah task, update status (drag di kanban).
- Komentar & mention rekan tim, unggah lampiran.
- Terima notifikasi assignment & deadline.

### Viewer
- Akses baca-saja terhadap proyek & progress.

---

## C. Skill Non-Teknis (proses tim)

- **Manajemen proyek Agile**: sprint, backlog, prioritas.
- **Komunikasi**: penulisan task/requirement yang jelas.
- **Code review**: standar kualitas, keamanan, konsistensi.
- **Dokumentasi**: menjaga DESIGN.md/README tetap up-to-date.

---

## D. Definition of Done (DoD) per Fitur

Sebuah fitur dianggap selesai bila:
- [ ] Migrasi + model + relasi dibuat & teruji.
- [ ] Policy/otorisasi diterapkan dan diuji.
- [ ] Validasi input (Form Request) lengkap.
- [ ] UI Blade/Tailwind responsif & aksesibel.
- [ ] Feature test menutup happy-path + edge case utama.
- [ ] Tidak ada N+1 query (eager loading dicek).
- [ ] Dokumentasi/README diperbarui bila perlu.

---

## E. Peta Skill → Milestone

| Milestone | Skill dominan |
|-----------|---------------|
| M1 Fondasi | Eloquent, migrasi, auth, RBAC, Form Request |
| M2 Kolaborasi | React (kanban drag-drop, modal), Events/Listeners, notifikasi |
| M3 Insight | Query agregasi, **Chart.js** (grafik), file storage |
| M4 Polish | Broadcasting/realtime, testing, deployment, optimasi |
