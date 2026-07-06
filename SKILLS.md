# SKILLS — Kapabilitas Sistem & Tim

Dokumen ini merinci kapabilitas (skill) yang dibutuhkan untuk **membangun** dan **mengoperasikan** Sistem Manajemen Proyek/Task berbasis Laravel + Tailwind.

Referensi arsitektur: [DESIGN.md](DESIGN.md) · Peran pembangun: [AGENTS.md](AGENTS.md)

---

## A. Skill Teknis (untuk membangun sistem)

### 1. Backend — Laravel / PHP
- **PHP 8.2+**: tipe data, enum, match expression, readonly property.
- **Eloquent ORM**: relasi (hasMany, belongsToMany, morphMany), eager loading, scope, accessor/mutator.
- **Migrasi & Seeder**: skema DB, foreign key, index, factory untuk data dummy.
- **Autentikasi & Otorisasi**: Laravel Breeze/Fortify, Policies, Gates, middleware.
- **Validasi**: Form Request, custom rules.
- **Queue & Jobs**: Redis queue, job async untuk email/notifikasi.
- **Events & Listeners**: pola event-driven untuk log aktivitas & notifikasi.
- **Testing**: PHPUnit/Pest — Feature test & Unit test.

### 2. Frontend — Blade + Tailwind + Livewire
- **Blade**: components, slots, layouts, directives (`@can`, `@foreach`).
- **Tailwind CSS**: utility classes, responsive design, dark mode, komponen reusable.
- **Livewire**: komponen dinamis, wire:model, event, lifecycle hooks.
- **Alpine.js**: interaksi ringan (dropdown, modal, drag-drop kanban).
- **UX dasar**: form yang jelas, loading state, empty state, feedback aksi.

### 3. Database & Infrastruktur
- **SQL** (MySQL/PostgreSQL): query, join, index, normalisasi.
- **Redis**: caching, session, queue.
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
| M2 Kolaborasi | Livewire, Alpine (kanban), Events/Listeners, notifikasi |
| M3 Insight | Query agregasi, charting, file storage |
| M4 Polish | Broadcasting/realtime, testing, deployment, optimasi |
