# O1-PRD — Sistem AI Preneur Terautomasi (Modular)

**Objective O1**: Membangun sistem manajemen bisnis terotomasi untuk ekosistem AI Preneur yang mencakup pipeline endorsement, Kanban produksi, OKR & KPI, pembukuan, insight, script, serta manajemen user berbasis peran — dengan arsitektur Laravel + Inertia + Vue 3 yang siap produksi di shared hosting.

**Status keseluruhan**: ✅ M1-M5 selesai (16 modul, 275 tes). ⏳ M6 hardening + audit log.

**Approval/QC**: Gilang (scope, prioritas, budget, security, production).

---

## Peta KR → Modul

| Key Result | Modul | Status | Fase |
|---|---|---|---|
| **KR 1.1** Dashboard & Reporting | Dashboard, PDF Report | ✅ | F1 |
| **KR 1.2** Pipeline Endorsement | Sales Pipeline (board `sales`) | ✅ | F1 |
| **KR 1.3** Kanban Produksi | Kanban (board/kolom dinamis + kartu) | ✅ | F2 |
| **KR 1.4** Strategi & Kinerja | OKR, KPI Board, KPI Orang, Tracking | ✅ | F5 |
| **KR 1.5** Keuangan | Pembukuan (Chart.js) | ✅ | F2 |
| **KR 1.6** Integrasi Data | Insight (API), Script (API), MCP | ✅ | F2/F6 |
| **KR 1.7** User & Akses | User CRUD, Akses (RBAC), Auth | ✅ | F1 |
| **KR 1.8** Modul Pendukung | Content, Order, Absensi, Mindmap, Upload | ✅/⏳ | F3-F7 |

---

## KR 1.1 — Dashboard & Reporting

**Tujuan**: Satu tampilan ringkasan lintas modul yang memberi visibilitas instan terhadap pipeline, Kanban, dan keuangan.

### Scope (selesai ✅)
- Dashboard ringkasan: total entri, omzet IDR/USD/gabungan, breakdown pipeline per board, distribusi progress Kanban, ringkasan Pembukuan.
- Filter bulan (Jan-Des, pilihan tahun sekarang).
- StatCard modular untuk angka kunci.
- Course home redirect (`/` → home route sesuai peran).

### Dependency
- **Input dari**: Pipeline (data entri & omzet), Kanban (progress kartu), Pembukuan (transaksi bulanan).
- **Tidak ada downstream** — Dashboard konsumen murni.

### Acceptance Criteria
- [x] Menampilkan jumlah entri per board (pipeline + kanban).
- [x] Menampilkan total omzet IDR, USD, dan gabungan (kurs otomatis).
- [x] Breakdown progress Kanban per board dalam chart/ringkasan.
- [x] Filter bulan berfungsi tanpa reload penuh.
- [x] Data disaring server sesuai peran (mis. omzet tak muncul untuk staff).

---

## KR 1.2 — Pipeline Endorsement

**Tujuan**: Mencatat dan mengelola semua deal endorsement AI Preneur dari lead sampai closing — menggantikan Google Sheets.

### Scope (selesai ✅)
- CRUD entri via modal: account (FK/AI Preneur), endorse, output (multi-tag), jenis (endorse/coaching_1on1/coaching_perusahaan/agensi/speaker), tanggal posting/payment, status payment (belum/dp/lunas), jumlah IDR & USD, DP 1-3, kontak (WA/Gmail/IG), link, notes, ke_gilang, catatan.
- Filter: account, progress, payment, output, search.
- Report PDF (blade + DomPDF): omzet per board / semua.
- Soft delete + restore.

### Dependency
- **Input dari**: User (assigned_to), Output (pivot), Categories (board=`sales`).
- **Memberi ke**: Dashboard, OKR (omzet dari `transactions`).

### Acceptance Criteria
- [x] CRUD entri lengkap dengan validasi semua field.
- [x] Filter berfungsi (akumulatif: account + progress + payment + output + search).
- [x] Report PDF menghasilkan data benar dan layout rapi.
- [x] Hanya `canManage()` yang boleh mutasi; staff/admin dapat read-only atau 403.
- [x] Soft delete: terhapus tidak muncul di list, bisa direstore.

---

## KR 1.3 — Kanban Produksi

**Tujuan**: Papan kerja bergaya Trello untuk alur produksi konten — dinamis, kolaboratif, terukur.

### Scope (selesai ✅)
- **Board & kolom dinamis**: owner/it/manager CRUD board (`categories`) dan kolom (`board_columns`). Warna kolom dari CSS class (disafelist).
- **Drag-drop** kartu antar kolom (optimistic UI, PATCH JSON). Progress tersimpan sebagai key kolom. Masuk kolom terakhir → `completed_at` terisi.
- **Fitur kartu penuh**:
  - Label warna (pilih-satu, snapshot, dikelola di halaman Labels oleh owner).
  - Deadline + highlight merah bila lewat.
  - Deskripsi (textarea).
  - Checklist/todo (PATCH JSON, checkbox inline).
  - Lampiran file (upload, max 10MB, disk public).
  - Komentar (semua peran bisa, termasuk staff yang ditugasi).
  - Arsip (kartu selesai → arsipkan; bisa dikembalikan).
  - Reminder deadline (lonceng global).
- **Galeri board** (`/pipelines/kanban`) — tampilan grid board yang bisa diakses.
- **Filter**: search, assigned_to, label.

### Aturan inti
- **"Selesai" = `completed_at` terisi** (bukan flag, bukan nama kolom). Diisi saat kartu masuk kolom paling kanan.
- Kartu tanpa deadline tidak dihitung di KPI/OKR.
- Stempel `completed_at` pertama dipertahankan (anti-"rapikan" kartu telat).

### Dependency
- **Input dari**: Categories (board), BoardColumns (kolom), Labels (definisi), Users (PJ).
- **Memberi ke**: Dashboard (progress), OKR (kartu tertaut KR), KPI (ketepatan, rapor orang).

### Acceptance Criteria
- [x] CRUD board & kolom (hanya canManage).
- [x] Drag-drop kartu menyimpan progress secara akurat.
- [x] Checklist, deadline, label, deskripsi, lampiran, komentar berfungsi.
- [x] Highlight merah untuk kartu lewat deadline.
- [x] Arsip + restore kartu.
- [x] Staff bisa kelola kartu di board kanban + berkomentar; tidak bisa kelola board/kolom/kartu Sales.
- [x] Galeri board menampilkan semua board sesuai akses peran.

---

## KR 1.4 — Strategi & Kinerja (OKR + KPI)

**Tujuan**: Goal perusahaan per kuartal terhubung langsung ke eksekusi di Kanban, dengan pengukuran performa tim yang akurat.

### OKR (selesai ✅ | owner + manager)

- **Objective** per kuartal: title, target omzet (opsional), PIC omzet, prioritas (Urgent/Penting).
- **Key Result** 3 sumber:
  - `auto` — realisasi dari Insight (view/subscriber) & Pembukuan (omset). Tidak bisa diketik.
  - `manual` — angka diisi sendiri.
  - `kartu` — realisasi = jumlah kartu Kanban todolist tertaut yang selesai.
- Progress Objective = rata-rata persen KR (masing-masing capped 100%).
- Grafik tren 6 kuartal.
- Salin KR dari kuartal lalu.
- **Jembatan goal → eksekusi**: goal ditulis di OKR; langkah pencapaian = kartu Kanban tertaut ke KR.
- **Notifikasi OKR** (database, persisten, tanpa cron):
  - `okr_assignment` — PIC omzet, PIC card, PJ KR.
  - `okr_perubahan` — PIC lama + baru saat edit.
  - `okr_selesai` — pemilik OKR saat kartu selesai.
  - `okr_deadline` — pengingat deadline (maks 1/kartu/hari, lazy creation).

### KPI (selesai ✅)

- **Per Board**: target kartu selesai + rekap ketepatan (tepat/terlambat/lewat deadline).
- **Per Orang**: rapor kerja (kartu, selesai, tepat/telat, rasio, rata-rata keterlambatan) — basis `assigned_to`. Owner/manager lihat semua; peran lain hanya dirinya (disaring server).
- Ketepatan 4 status: tepat / terlambat / lewat / tak dinilai.

### Tracking (selesai ✅ | owner + manager)
- Ringkasan eksekutif read-only per board: total kartu, selesai, dalam proses, overdue, tanpa deadline.

### Acceptance Criteria
- [x] Objective & KR CRUD dengan validasi source.
- [x] Realisasi auto terhitung benar (dari Insight & Pembukuan).
- [x] Realisasi kartu = jumlah kartu tertaut selesai (akurat).
- [x] Progress Objective tidak tembus >100% per KR.
- [x] Grafik tren 6 kuartal menampilkan data historis.
- [x] KPI board dan rapor orang menampilkan data per kuartal.
- [x] Rapor per orang: staff hanya melihat barisnya sendiri.
- [x] Notifikasi OKR terkirim dan terbaca di lonceng.
- [x] Notifikasi tidak terkirim ke pelaku perubahan sendiri.
- [x] Penautan kartu OKR → Kanban dua arah benar.

---

## KR 1.5 — Pembukuan

**Tujuan**: Rekap keuangan bulanan dengan grafik — pemasukan, pengeluaran, laba, inventaris.

### Scope (selesai ✅)
- CRUD transaksi: type (pemasukan/pengeluaran), category, amount_idr, date, description.
- CRUD inventaris: name, qty, unit_value_idr, month.
- Grafik (Chart.js): ringkasan bulanan, komposisi per kategori, tren laba.
- Report PDF.
- Kurs USD→IDR otomatis (open.er-api.com, cache 12 jam, fallback Rp 16.000).

### Dependency
- **Input dari**: Transactions, Inventories.
- **Memberi ke**: Dashboard (ringkasan), OKR (realisasi omset objective).

### Acceptance Criteria
- [x] CRUD transaksi & inventaris.
- [x] Grafik Chart.js merender data bulanan dengan benar.
- [x] Report PDF akurat.
- [x] Hanya owner + manager + it (dikunci keras di `canSee()`, bukan `role_menu_access`).
- [x] Kurs otomatis USD→IDR, dengan fallback.

---

## KR 1.6 — Integrasi Data (Insight + Script + MCP)

**Tujuan**: Data sosial media dan naskah masuk otomatis dari VPS; strategi OKR bisa diakses AI via MCP.

### Insight (selesai ✅)
- API endpoint `POST /api/insights` (bearer token, `throttle:30,1`).
- Menerima data konten (`insight_contents`) dan akun (`insight_accounts`).
- Upsert idempoten — cron re-fetch data yang sama aman.
- Menjadi sumber realisasi OKR view & subscriber.
- Halaman `/insight` menampilkan data per akun & konten.

### Script (selesai ✅)
- API endpoint `POST /api/scripts` (bearer token, `throttle:30,1`).
- Menerima batch naskah per brand + tanggal.
- Replace-paket strategy: hapus batch lama, insert baru.
- Halaman `/script` + `/script/{brand}`: grid folder naskah.

### MCP Server (selesai ✅)
- Node.js MCP server di `/mcp-server/`.
- 4 tool: `list_okr`, `create_objective`, `create_key_result`, `link_task_to_kr`.
- Menggunakan rumus realisasi yang sama dengan aplikasi.

### Acceptance Criteria
- [x] API ingest Insight menerima data dan upsert benar.
- [x] API ingest Script menerima data dan replace batch benar.
- [x] Token auth timing-safe (`hash_equals`), bedakan 401 vs 503.
- [x] Rate limiting 30 req/menit.
- [x] Halaman Insight & Script menampilkan data terbaru.
- [x] MCP tools mengembalikan data yang sama dengan aplikasi.

---

## KR 1.7 — User & Akses (Auth + RBAC)

**Tujuan**: Kontrol akses berbasis peran yang tegas di server — bukan hanya menyembunyikan tombol di UI.

### Scope (selesai ✅)
- **5 peran**: owner, it, manager, admin, staff.
- **Auth**: register (langsung jadi staff), login, logout, session (Laravel session).
- **RBAC dua lapis**:
  1. `EnsureMenuAccess` middleware — cek akses menu + `canManage()` untuk route mutasi.
  2. `User::canSee($menu)` / `canManage()` / `canManageBoard($cat)` / `canManageMenu($menu)`.
- **Halaman Akses** (`/akses`): owner/it mengelola `role_menu_access` per peran.
- **Shared props**: `auth.user` (id, role, canManage, peta menu), `notifications`, `reminders`.
- **Hard lock**: `okr`, `pembukuan`, `tracking` = owner + manager (kode, bukan `role_menu_access`).
- **Imunitas owner**: `canSee()` selalu return true untuk owner.
- **Penyaringan data di server**: props Inertia yang tak boleh dilihat tidak dikirim, bukan hanya `v-if`.

### Acceptance Criteria
- [x] Register → login → redirect ke home route sesuai peran.
- [x] Menu di sidebar hanya menampilkan yang diizinkan.
- [x] Akses halaman terlarang → 403 (bukan redirect login).
- [x] Route mutasi terlarang → 403 untuk non-canManage.
- [x] Halaman Akses berfungsi CRUD `role_menu_access`.
- [x] `okr`/`pembukuan`/`tracking` tak bisa dibuka oleh admin/staff walau ada di `role_menu_access`.
- [x] Staff hanya melihat rapor KPI dirinya sendiri.
- [x] Panel KPI di Kanban hanya tampil untuk canManage.

---

## KR 1.8 — Modul Pendukung

### Content (selesai ✅)
- Perencanaan konten mingguan: comp, jenis postingan, kategori, inti pesan, hook, brief, script remake, editor, progress, tanggal upload, link hasil.
- CRUD tabel.

### Order (selesai ✅)
- Pesanan customer: tipe order, account, deadline, nama customer, kontak, tipe pembayaran, total IDR/USD, bukti bayar, invoice.
- CRUD + pivot output.

### Absensi (selesai ✅)
- Pengajuan cuti/sakit: type, start_date, end_date, reason, attachment.
- Status: pending/approved/rejected.
- Semua peran bisa mengajukan; approve/reject oleh yang berwenang.

### Mindmap (selesai ✅)
- Galeri mindmap (`/mindmaps`) + editor (`/mindmaps/{id}/edit`).
- 5 template framework siap pakai.
- Library: simple-mind-map.

### Upload (⏳ template)
- Status: **TEMPLATE — belum aktif**. Hanya UI statis, tidak ada backend.
- Target: YouTube (OAuth upload via VPS agent), TikTok, Instagram.
- Arsitektur: Laravel kirim draft ke VPS agent → agent panggil API platform — Laravel tidak pernah langsung panggil API eksternal.

### Audit Log (⏳ belum ada)
- Lihat [O1-ARSITEKTUR-SISTEM.md §Audit Log](O1-ARSITEKTUR-SISTEM.md).

### Acceptance Criteria
- [x] Content: CRUD tabel, filter berfungsi.
- [x] Order: CRUD pesanan + pivot output, filter.
- [x] Absensi: submit request, approve/reject, list sendiri & semua (berdasarkan role).
- [x] Mindmap: galeri + editor + 5 template.
- [ ] Upload: kirim draft → VPS agent → platform API (belum dibangun).
- [ ] Audit log: rekam semua aksi mutasi (belum dibangun).

---

## Di Luar Lingkup (Non-Goals)

- Integrasi payment gateway.
- Notifikasi email / realtime / push.
- Reorder kolom via drag antar-list (urutan = urutan pembuatan).
- Manajemen aset media.
- Queue, events, broadcasting, Actions/Services layer.
- Policy per-model — pakai middleware + method User.

---

## Kebutuhan Non-Fungsional

| Aspek | Standard |
|---|---|
| **Auth** | Laravel session, CSRF, bcrypt |
| **Otorisasi** | Dua lapis: `EnsureMenuAccess` + `User::canManage()` |
| **Validasi** | `$request->validate()` + Rule::in dinamis |
| **Responsif** | Desktop + tablet |
| **Lokalisasi** | Bahasa Indonesia, format Rupiah & USD |
| **Kurs** | open.er-api.com, cache 12 jam, fallback Rp 16.000 |
| **File upload** | Max 10MB, disk `public`, `storage:link` |
| **Soft delete** | Pipeline (dipulihkan) |
| **Rate limit API** | 30 req/menit per endpoint |
| **Cache Inertia** | `Cache-Control: no-store` untuk X-Inertia JSON |
| **Build** | `npm run build` lokal, upload `public/build/` |

---

## Definisi Selesai (Definition of Done)

| # | Kriteria |
|---|---|
| 1 | Migrasi + model + relasi dibuat (satu perubahan = satu file baru). |
| 2 | Otorisasi (menu + canManage) diterapkan & diuji lintas peran. |
| 3 | Validasi input lengkap di server. |
| 4 | Halaman Vue SFC `<script setup>` responsif. |
| 5 | Tidak ada N+1 (eager loading dicek). |
| 6 | `npm run build` sukses; warna dinamis ter-safelist. |
| 7 | Tes tulis / smoke test HTTP lolos. |
| 8 | Dokumentasi diperbarui bila ada perubahan arsitektur. |

---

## Status per Modul

| KR | Modul | Build | Tes | Deploy | Catatan |
|---|---|---|---|---|---|
| 1.1 | Dashboard | ✅ | ✅ | ✅ | |
| 1.2 | Pipeline | ✅ | ✅ | ✅ | |
| 1.3 | Kanban | ✅ | ✅ | ✅ | |
| 1.4 | OKR + KPI | ✅ | ✅ | ✅ | OKR lengkap dgn notifikasi |
| 1.5 | Pembukuan | ✅ | ✅ | ✅ | |
| 1.6 | Insight + Script + MCP | ✅ | ✅ | ✅ | |
| 1.7 | User + Akses | ✅ | ✅ | ✅ | |
| 1.8 | Content, Order, Absensi | ✅ | ✅ | ✅ | |
| 1.8 | Mindmap | ✅ | ✅ | ✅ | |
| 1.8 | Upload | ⏳ | ❌ | ❌ | Template, butuh YouTube agent |
| 1.8 | Audit Log | ❌ | ❌ | ❌ | Belum dibangun |
| — | Hardening (M6) | ⏳ | — | — | Logging, monitoring, backup |

---

## Selanjutnya

- [O1-ARSITEKTUR-SISTEM.md](O1-ARSITEKTUR-SISTEM.md) — Arsitektur, data model, auth/role, API standard, audit log, keamanan.
- [O1-BACKLOG-ROADMAP.md](O1-BACKLOG-ROADMAP.md) — Dependency map, roadmap 7 fase, backlog + acceptance criteria.
- [O1-RISIKO-APPROVAL.md](O1-RISIKO-APPROVAL.md) — Risiko, tools, keputusan butuh approval Gilang.
