# SKILLS — Kapabilitas Sistem & Tim

Kapabilitas untuk **membangun** dan **mengoperasikan** System AI Preneur (Laravel + Inertia + React).

Referensi arsitektur: [DESIGN.md](DESIGN.md) · Peran pembangun: [AGENTS.md](AGENTS.md)

---

## A. Skill Teknis (untuk membangun sistem)

### 1. Backend — Laravel / PHP
- **PHP 8.5**: enum, match, readonly, attribute (`#[Fillable]`).
- **Eloquent ORM**: relasi (hasMany, belongsTo, belongsToMany), eager loading (hindari N+1), accessor (mis. `url` pada attachment), cast json/date.
- **Inertia (server)**: `Inertia::render()`, shared props via `HandleInertiaRequests`, redirect → reload props.
- **Migrasi & Seeder**: skema, foreign key + cascade, index, seeder data contoh.
- **Otorisasi**: middleware kustom (`EnsureMenuAccess`) + method peran (`canSee`/`canManage`) — bukan Policy per-model.
- **Validasi**: `$request->validate()` / Rule::in dinamis (progress mengikuti kolom board).
- **File storage**: disk `public` + `storage:link` untuk lampiran.
- **PDF**: barryvdh/laravel-dompdf (blade report → PDF).

### 2. Frontend — Inertia + React + Chart.js + Tailwind
- **React 19**: komponen fungsional, hooks (`useState`/`useEffect`), props dari Inertia.
- **Inertia (client)**: `useForm` (submit + errors + file upload `forceFormData`), `router` (get/patch/delete, `preserveScroll/State`), `<Link>`, `usePage()` (shared props).
- **Chart.js** (`react-chartjs-2`): bar/line/pie untuk pembukuan (omzet per bulan, komposisi).
- **Tailwind v4**: utility classes, responsive, `@source inline(...)` untuk safelist warna dinamis dari DB.
- **Vite**: entry `app.jsx`, `import.meta.glob` untuk resolusi halaman Inertia.
- **UX**: modal, optimistic UI (drag-drop), loading/empty state, toast flash.

### 3. Database & Infrastruktur
- **SQLite** (WAL): dev lokal; deploy via import `.sql`.
- **Cache/session**: driver default (tanpa Redis).
- **Git**: branching, commit rapi.
- **Deploy**: build `public/build/` di laptop, upload; `.env` produksi; `storage:link` di server.

---

## B. Skill Fungsional (kapabilitas aplikasi per peran)

Lihat matriks lengkap di [PRD.md](PRD.md) §3.

### super_admin / it
- Akses semua menu; CRUD pipeline, board & kolom kanban, user.
- Kelola kartu: deadline, arsip, label, deskripsi, lampiran, komentar, checklist.
- Report PDF, dashboard, pembukuan.

### admin
- Lihat menu Script & Kanban (baca saja).

### editor / staff
- Lihat Kanban (baca saja) + **berkomentar** pada kartu (termasuk yang ditugaskan kepadanya).

---

## C. Skill Non-Teknis (proses tim)

- **Komunikasi**: penulisan requirement/task yang jelas.
- **Code review**: benar (edge/null/otorisasi), aman (secret, injection, path traversal), maintainable, minim (tanpa abstraksi berlebih).
- **Dokumentasi**: jaga README/PRD/DESIGN tetap selaras dengan kode.

---

## D. Definition of Done (per fitur)

- [ ] Migrasi + model + relasi dibuat.
- [ ] Otorisasi (menu + `canManage`) diterapkan & diuji (super_admin vs editor/staff → 403 yang benar).
- [ ] Validasi input lengkap.
- [ ] Halaman/komponen React responsif; `className`, bukan Blade.
- [ ] Tidak ada N+1 (eager loading dicek).
- [ ] `npm run build` sukses; warna dinamis ter-safelist.
- [ ] Verifikasi jalan (smoke test HTTP / self-check).
- [ ] Dokumentasi diperbarui bila perlu.

---

## E. Peta Skill → Milestone

| Milestone | Skill dominan |
|-----------|---------------|
| M1 Fondasi | Eloquent, migrasi, auth, middleware peran, validasi |
| M2 Modul | React + Chart.js (pembukuan), kanban dinamis, user CRUD |
| M3 SPA | Inertia (server & client), resolusi halaman, shared props |
| M4 Kartu | file storage, komentar/otorisasi, useForm upload, optimistic UI |
| M5 Deploy | build asset, import SQL, `storage:link`, hardening |
