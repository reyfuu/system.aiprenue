# AGENTS — System AI Preneur

Konvensi proyek untuk coding agent (Claude Code dsb.) + peran pembangun sistem.

**System AI Preneur** = aplikasi manajemen bisnis untuk ekosistem AI Preneur: **Dashboard, Pipeline, Kanban, Pembukuan, Script, User** — dengan hak akses per peran.

Referensi: [DESIGN.md](DESIGN.md) · [SKILLS.md](SKILLS.md) · [PRD.md](PRD.md)

---

## Stack (WAJIB dipatuhi agent)

- **Backend**: Laravel 13, PHP 8.5. SQLite (WAL) untuk dev; deploy via import `.sql`.
- **Frontend**: **Inertia.js + Vue 3** (SPA), Tailwind v4, Vite. Grafik: **Chart.js** via `vue-chartjs` (khusus Pembukuan).
- **Tanpa**: React, Livewire, Alpine, queue, events/listeners, Policies, layer Actions/Services, broadcasting. Dijaga sederhana.

> **Kenapa Vue, bukan React?** Produksi di shared hosting; tim memilih Vue sebagai adapter Inertia. Aset tetap di-build di laptop (`npm run build`) lalu di-upload — server tak pernah menjalankan Node.

---

## Modul

| Modul | Route | Status |
|-------|-------|:------:|
| Dashboard | `/dashboard` | ✅ |
| Sales Pipeline (1 board `sales`, stage Lead→Deal, tanpa galeri) | `/pipelines` | ✅ |
| Kanban (board/kolom dinamis, kartu lengkap) | `/pipelines/kanban` | ✅ |
| Pembukuan (Chart.js) | `/pembukuan` | ✅ |
| Script (template folder) | `/script` | ✅ |
| OKR (Objective+KR, /okr) | `/okr` | ✅ |
| KPI board + rapor per orang | `/kpi` | ✅ |
| Insight sosial media | `/insight` | ✅ |
| User management | `/users` | ✅ |

Peran: `owner`, `it` (penuh) · `manager` (operasional + OKR/KPI/Pembukuan/Tracking, tanpa User) · `admin` (Pipeline/Kanban/Content/Insight/KPI, kelola kartu) · `staff` (Kanban/Mindmap, kelola kartu di board kanban + rapor KPI sendiri). Skema DB: [Schema.md](Schema.md). Matriks: [PRD.md](PRD.md) §3.

---

## Konvensi Proyek (untuk coding agent)

**Backend**
- Controller mengembalikan `Inertia::render('Page', $props)` (bukan Blade/JSON), kecuali report PDF (DomPDF) & aksi drag/todo (JSON).
- Otorisasi lewat middleware **`EnsureMenuAccess`** (akses menu) + **`User::canManage()`** (route mutasi). **Jangan** buat Policy per-model atau cek role manual di controller — pakai helper peran yang ada.
- Validasi via `$request->validate()`. `progress`/`category` string dinamis (Rule::in dari `board_columns`/`categories`).
- Migrasi: satu perubahan = satu file baru; jangan edit migrasi lama.
- Mass-assignment lewat `$fillable`; hormati soft delete pada `Pipeline`.

**Frontend**
- Komponen halaman = **Vue 3 SFC** `<script setup>` di `resources/js/Pages/*.vue`. Resolusi nama halaman di `resources/js/app.js` (glob `.vue`).
- Bungkus halaman ber-sidebar dengan `<Layout title="…">`; modal pakai `ModalWrap.vue`.
- **Inertia Vue**: `useForm` (field top-level, `v-model="form.x"`, `form.post/put`, `form.errors`, `form.processing`), `router` (get/patch/delete + `preserveScroll/State`), `<Link>`, `usePage()`.
- Kelas dinamis dari DB (warna kolom/label) **wajib** ada di safelist `resources/css/app.css` (`@source inline(...)`).
- **Komentar kode**: Bahasa Indonesia, rinci (script logika + tiap seksi template).

**Otorisasi & data (WAJIB — gerbang di server)**
- Batasan bukan sekadar `v-if`/tombol tersembunyi: props Inertia terbaca di source. Data yang tak boleh dilihat peran tertentu **jangan dikirim** dari controller (kirim `null`/disaring), bukan disembunyikan di Vue. Contoh: `quarterStats` di Kanban, rapor KPI per orang.
- Batasan pilihan (mis. label maks 1) ditegakkan di validasi server juga, bukan cuma di picker.
- `okr`/`pembukuan`/`tracking` dikunci owner+manager di `User::canSee()` — jangan andalkan `role_menu_access` untuk ketiganya.

**OKR / KPI / ketepatan waktu**
- **"Selesai" = `completed_at` terisi**, bukan flag `done`, bukan nama kolom. Diisi saat kartu masuk kolom terakhir board. Rumus di `Pipeline::ketepatan()` — jangan tulis ulang.
- Kartu masuk kuartal berdasarkan **`deadline`**; kartu tanpa deadline tak dihitung. Konsisten di Kanban, KPI, rapor.
- KR `source` ∈ auto/manual/kartu — string, bukan enum. Realisasi auto/kartu **dihitung**, tak diketik; `updateActual` menolak keduanya.
- Rumus dipakai ulang lintas halaman (`KpiController::statistik`, `OkrMetrics::realisasi`, `KeyResult::actual`), tak disalin. Hitung realisasi **sekali per kuartal** lalu oper (hindari N+1).

**Umum**
- Gaya kode PHP: PSR-12, jalankan `./vendor/bin/pint`.
- Build: `npm run build` sebelum commit bila mengubah frontend.
- Migrasi: satu perubahan = satu file baru; idempoten per kolom (`Schema::hasColumn`).
- Commit: pesan singkat & imperatif.
- Deploy butuh `php artisan storage:link` (lampiran kartu).

---

## Daftar Peran (agent / manusia)

1. **Architect** — struktur, keputusan teknis, ERD; jaga [DESIGN.md](DESIGN.md).
2. **Backend** — model, migrasi, controller Inertia, middleware otorisasi, validasi.
3. **Frontend** — komponen Vue (Pages, Layout, modal), Chart.js (Pembukuan), Tailwind, UX (optimistic drag-drop, modal, empty/loading state).
4. **Database** — skema, index, optimasi query, integritas, deteksi N+1.
5. **QA / Test** — verifikasi DoD (SKILLS.md §D), smoke test HTTP per peran.
6. **Security** — audit otorisasi (menu + canManage), validasi, mass-assignment, CSRF/XSS, upload aman.
7. **DevOps** — `.env` produksi, build asset, import SQL, `storage:link`, backup.

---

## Penggunaan Coding Agent (Claude Code)

Contoh instruksi:
- *"Sebagai Backend Agent, tambah fitur X: migrasi + model + controller `Inertia::render` + otorisasi via EnsureMenuAccess."*
- *"Sebagai Frontend Agent, buat halaman/komponen Vue SFC (`<script setup>`) sesuai konvensi, komentar Bahasa Indonesia."*
- *"Sebagai QA Agent, smoke test route sebagai owner & staff (cek 200 dan 403 yang benar)."*

Agent harus selalu: (1) patuhi **Konvensi Proyek**, (2) rujuk DESIGN.md/SKILLS.md, (3) verifikasi (build + smoke test) setelah perubahan non-trivial.
