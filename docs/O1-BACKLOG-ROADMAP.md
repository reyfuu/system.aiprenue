# O1-Backlog & Roadmap — Sistem AI Preneur Terautomasi

Dependency map, roadmap 7 fase, backlog, acceptance criteria, dan gate tiap fase.

Referensi: [O1-PRD-MODULAR.md](O1-PRD-MODULAR.md) · [O1-ARSITEKTUR-SISTEM.md](O1-ARSITEKTUR-SISTEM.md).

---

## 1. Peta Dependency Antar Modul

```
                       ┌─────────────────────┐
                       │  Users & Auth (F1)  │
                       │  role_menu_access   │
                       └──────┬──────────────┘
                              │ semua modul butuh user & otorisasi
        ┌─────────────────────┼─────────────────────┐
        ▼                     ▼                      ▼
┌───────────────┐   ┌─────────────────┐   ┌─────────────────┐
│ Dashboard(F1) │   │ Pipeline (F1)   │   │ OKR + KPI (F3)  │
│ konsumen saja  │   │ board sales     │   │ objectives, KR  │
└───────┬───────┘   └────────┬────────┘   └────────┬────────┘
        │                    │                      │
        │                    │   ┌──────────────────┘
        ▼                    ▼   ▼
┌─────────────────────────────────────────┐
│           Kanban (F2)                    │
│  board dinamis, kolom, kartu dengan     │
│  label, deadline, checklist, lampiran,  │
│  komentar, arsip                        │
└──────────┬────────────┬─────────────────┘
           │            │
           ▼            ▼
┌──────────────┐ ┌──────────────────────┐
│ Pembukuan(F2)│ │ Insight + Script(F2) │
│ transaksi,   │ │ API ingest, MCP(F4)  │
│ inventaris   │ └──────────────────────┘
└──────────────┘
           │
           ▼
┌────────────────────────────────────────────┐
│          Supporting Modules (F3-F5)          │
│  Content, Order, Absensi, Mindmap,          │
│  Tracking, Upload (F5-F6), Audit Log (F6)   │
└────────────────────────────────────────────┘
           │
           ▼
┌────────────────────────────────────────────┐
│           Hardening (F7)                    │
│  Security headers, monitoring, backup,     │
│  rate limiting, session hardening, PT       │
└────────────────────────────────────────────┘
```

### Matriks dependency: siapa butuh siapa

| Modul (butuh) | Dependensi (dibutuhkan) |
|---|---|
| **Semua modul** | Users (auth), role_menu_access (RBAC) |
| **Dashboard** | Pipeline, Kanban, Pembukuan |
| **Pipeline** | Users (assigned_to, created_by), Output, Categories |
| **Kanban** | Users, Categories, BoardColumns, Labels |
| **OKR + KPI** | Users, Objective, KeyResult, Pipelines (kartu tertaut), Insight, Pembukuan |
| **Pembukuan** | Users (created_by) |
| **Insight** | API token, InsightAccount/Content |
| **Script** | API token, Script |
| **Content, Order, Mindmap** | Users |
| **Absensi** | Users |
| **Tracking** | Categories (board), Kanban (pipelines) |
| **Upload** | Users, API token (YouTube OAuth via VPS) |
| **Audit Log** | Users, semua model yang akan diaudit |
| **MCP Server** | OKR (objectives, key_results), Pipelines |

---

## 2. Roadmap 7 Fase

### Fase 1 (F1) — Fondasi ✅

**Output minimum**:
- Register, login, logout, session auth.
- 5 peran: owner, it, manager, admin, staff.
- Middleware `EnsureMenuAccess` + method `User::canSee()` / `canManage()`.
- Halaman User CRUD (`/users`) — owner/it.
- Halaman Akses (`/akses`) — `role_menu_access` CRUD.
- Sidebar dinamis sesuai peran.
- Pipeline CRUD (`/pipelines`) — board `sales`, tabel CRUD via modal, filter.
- Report PDF omzet (DomPDF).
- Dashboard ringkasan (`/dashboard`).
- 5 tes: RootRoute, Register, Dashboard, SalesPipeline, UserCrud.

**Gate F1 → F2**: ✅ lulus — auth 5 peran berfungsi, otorisasi ditegakkan, pipeline CRUD + report PDF jalan.

---

### Fase 2 (F2) — Modul Inti ✅

**Output minimum**:
- Kanban board & kolom dinamis (`/pipelines/kanban`).
- Drag-drop kartu (PATCH JSON, optimistic UI).
- Galeri board.
- Fitur kartu: label, deadline, deskripsi, checklist, lampiran, komentar, arsip.
- Pembukuan (`/pembukuan`): CRUD transaksi + inventaris, Chart.js grafik, report PDF.
- Insight (`/insight`): tampilan data akun & konten.
- Script (`/script`): grid folder per brand.
- API ingest insight (`POST /api/insights`).
- API ingest script (`POST /api/scripts`).
- Kurs USD/IDR otomatis.
- 12 tes: Board, Column, PipelineCard, Comment, Attachment, DashboardOmzet, Pembukuan, dll.

**Gate F2 → F3**: ✅ lulus — Kanban dinamis kerja, drag-drop ok, semua fitur kartu jalan, API ingest siap, Pembukuan + Insight + Script tampil.

---

### Fase 3 (F3) — SPA Migration ✅

**Output minimum**:
- Migrasi Blade+Alpine → Inertia + Vue 3.
- Semua halaman sebagai Vue SFC `<script setup>`.
- Layout, Sidebar, ModalWrap komponen reusable.
- Shared props via `HandleInertiaRequests`.
- `app.js` entry dengan `import.meta.glob` resolusi halaman.
- Tailwind v4 + safelist warna dinamis.
- Content module (`/content`): perencanaan konten mingguan.
- Order module (`/orders`): pesanan customer.
- 5 tes: Content, Order, InertiaCacheHeader.

**Gate F3 → F4**: ✅ lulus — SPA berfungsi, semua halaman Vue, layout konsisten, Content & Order jalan.

---

### Fase 4 (F4) — Fitur Kartu Lengkap ✅

**Output minimum**:
- Manajemen label oleh owner (`/labels`).
- Snapshot label di kartu (pilih-satu, server-enforced).
- Highlight deadline lewat.
- Checklist/todo inline.
- Lampiran file (upload, preview, download, delete — max 10MB).
- Komentar kartu (semua peran kanban, termasuk staff ditugasi).
- Arsip kartu (archive + restore).
- Soft delete pipeline.
- Absensi module (`/absensi`): pengajuan cuti/sakit, approve/reject.
- 4 tes: CompletedAtFreeze, Label di Pipeline, Absensi.

**Gate F4 → F5**: ✅ lulus — fitur kartu lengkap, label+deadline+checklist+lampiran+komentar+arsip semua berfungsi.

---

### Fase 5 (F5) — Strategi & Kinerja ✅

**Output minimum**:
- OKR (`/okr`): Objective + Key Result per kuartal.
- 3 sumber realisasi: auto, manual, kartu.
- Jembatan goal→eksekusi: kartu Kanban tertaut ke KR.
- Progress Objective = rata-rata capped 100%.
- Grafik tren 6 kuartal + salin KR.
- Notifikasi OKR (database, 4 jenis, tanpa cron).
- KPI (`/kpi`): Per Board + Per Orang.
- Rapor per orang disaring server.
- Tracking (`/tracking`): ringkasan eksekutif read-only.
- Mindmap (`/mindmaps`): galeri + editor + 5 template.
- MCP server (`/mcp-server/`): 4 tool strategi OKR.
- 12 tes: Okr, KpiOrang, KanbanStaffAccess, Mindmap, Tracking, dll.

**Gate F5 → F6**: ✅ lulus — OKR+KPI+Tracking lengkap dengan notifikasi, jembatan goal→Kanban berfungsi, MCP siap, rapor akurat.

---

### Fase 6 (F6) — Automation & Audit ⏳

**Output minimum**:
- **Upload module — YouTube aktif**:
  - Backend: upload draft (judul, deskripsi, file video, jadwal) → kirim ke VPS agent.
  - VPS agent: OAuth YouTube `youtube.upload`.
  - Status tracking di UI (draft → uploading → published → gagal).
  - TikTok & Instagram = placeholder (soon).
- **Audit log system**:
  - Tabel `audit_logs` + model + trait `Auditable`.
  - Halaman `/audit` (owner + it): filter user, aksi, model, rentang tanggal.
  - Cakupan: Pipeline, BoardColumn, Category, Objective, KR, Transaction, Inventory, User.
- **Notifikasi persisten** (ekspansi dari notifikasi OKR):
  - Notifikasi untuk aksi penting: kartu deadline dekat, approve cuti, dsb.
- **Dashboard filter enhancement**: filter quarter, perbandingan antar periode.

**Gate F6 → F7**:
- [ ] YouTube upload end-to-end berfungsi (draft → VPS → YouTube → published).
- [ ] Audit log mencatat semua aksi mutasi.
- [ ] Halaman `/audit` dapat diakses owner + it.
- [ ] `npm run build` sukses.
- [ ] Tes baru lolos (audit log + upload).

---

### Fase 7 (F7) — Hardening & Go-Live 🔴

**Output minimum**:
- **Security hardening**:
  - HTTPS enforcement.
  - Security headers: CSP, HSTS, X-Frame-Options.
  - Session timeout: 15 menit.
  - Rate limiting: login (5/menit), register (3/jam).
- **Monitoring & logging**:
  - Log level `error` → Slack webhook.
  - Log rotation (daily, retain 30 hari).
  - Error tracking (Sentry atau Laravel Telescope).
- **Backup & recovery**:
  - Cron backup database harian.
  - Retain 7 hari.
- **Performance**:
  - Eager loading audit (cek N+1 di seluruh controller).
  - Query optimization: `EXPLAIN` di query berat (Dashboard, OKR realisasi).
- **Penetration testing**:
  - OWASP Top 10 checklist.
  - Tes manual: 403 di route terlarang, XSS di input, SQL injection, upload ekstensi berbahaya.

**Gate go-live**:
- [ ] Semua header keamanan aktif.
- [ ] SSL valid.
- [ ] Backup berjalan.
- [ ] Log error terkirim ke Slack.
- [ ] PT selesai, semua critical fixed.
- [ ] Gilang menyetujui go-live.
- [ ] Post-deploy smoke test: semua menu bisa diakses sesuai peran.

---

## 3. Backlog Detail per Fase

### Fase 6 — Backlog

#### B6.1 — Audit Log System

| ID | Task | AC | Prioritas |
|---|---|---|---|
| B6.1.1 | Migrasi `audit_logs`: tabel dengan kolom `user_id`, `user_role`, `action`, `model_type`, `model_id`, `model_name`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at` | Tabel terbuat, indeks di `(model_type, model_id)` dan `(user_id, created_at)` | P0 |
| B6.1.2 | Model `AuditLog` dengan `$fillable` + relasi `user()` | Model berfungsi, relasi eager-loadable | P0 |
| B6.1.3 | Trait `Auditable` yang dipasang di model: `Pipeline`, `BoardColumn`, `Category`, `Objective`, `KeyResult`, `BoardQuarterTarget`, `Transaction`, `Inventory`, `User`, `Content`, `Order` | Semua aksi create/update/delete tercatat | P0 |
| B6.1.4 | Method statis `AuditLog::record($action, $model)` yang mencatat perubahan | `old_values`/`new_values` akurat untuk update; create dan delete juga benar | P0 |
| B6.1.5 | Middleware atau helper untuk menangkap IP & user_agent dari `request()` | IP dan user agent tersimpan di log | P1 |
| B6.1.6 | Halaman `/audit` (Vue SFC: tabel + filter user, aksi, model, rentang tanggal, pagination 50) | Owner + it bisa akses, filter berfungsi, tidak bisa edit/delete | P0 |
| B6.1.7 | Tes: `AuditLogTest` — cek create, update, delete tercatat; cek akses admin/staff → 403 | Tes lolos | P0 |

#### B6.2 — Upload Module: YouTube Aktif

| ID | Task | AC | Prioritas |
|---|---|---|---|
| B6.2.1 | Endpoint `POST /upload` di `UploadController`: terima judul, deskripsi, file video (max 500MB), jadwal (opsional) | Form submit berhasil, file tersimpan sementara | P0 |
| B6.2.2 | Model `UploadDraft`: user_id, platform, judul, deskripsi, file_path, scheduled_at, status, youtube_video_id, error_message | Tabel + model siap | P0 |
| B6.2.3 | Logika kirim draft ke VPS agent (POST ke endpoint agent dengan data draft) | Request terkirim, status ter-update | P1 |
| B6.2.4 | Webhook dari VPS agent: update status (uploading → published / gagal) | Status draft ter-update otomatis | P1 |
| B6.2.5 | UI `Upload.vue`: form submit + progress upload + daftar draft dengan status | UX upload berfungsi | P0 |
| B6.2.6 | Tes: `UploadTest` — upload file, cek status, simulasikan webhook | Tes lolos | P1 |

#### B6.3 — Dashboard Enhancement

| ID | Task | AC | Prioritas |
|---|---|---|---|
| B6.3.1 | Filter quarter di Dashboard (Q1-Q4 + year) | Grafik dan ringkasan berubah sesuai quarter | P1 |
| B6.3.2 | Perbandingan quarter-over-quarter (growth %) | Angka pertumbuhan tampil | P2 |

### Fase 7 — Backlog

#### B7.1 — Security Hardening

| ID | Task | AC | Prioritas |
|---|---|---|---|
| B7.1.1 | HTTP response headers: `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `X-XSS-Protection: 1; mode=block` | Headers muncul di setiap response | P0 |
| B7.1.2 | CSP header via middleware (whitelist self + Cloudflare + Google Fonts + Chart.js CDN) | CSP aktif, tidak ada console error | P0 |
| B7.1.3 | Session lifetime 15 menit di `config/session.php` | Logout otomatis setelah 15 menit idle | P1 |
| B7.1.4 | Rate limiting login: 5/menit; register: 3/jam | Brute force login terhalang | P1 |

#### B7.2 — Monitoring

| ID | Task | AC | Prioritas |
|---|---|---|---|
| B7.2.1 | Slack webhook untuk log level `error` ke atas | Error kritis terkirim ke Slack | P1 |
| B7.2.2 | Log daily rotation, retain 30 hari (prod `.env`) | Log otomatis bersih | P1 |
| B7.2.3 | Health check endpoint `GET /health` (return status 200 + DB connection OK) | Monitoring uptime bisa dicek | P2 |

#### B7.3 — Backup

| ID | Task | AC | Prioritas |
|---|---|---|---|
| B7.3.1 | Shell script `mysqldump` harian via cron | File backup `.sql` tersimpan 7 hari | P0 |
| B7.3.2 | Restore script + dokumentasi | Bisa restore dari backup dalam <5 menit | P0 |

#### B7.4 — Performance & PT

| ID | Task | AC | Prioritas |
|---|---|---|---|
| B7.4.1 | Audit N+1 di semua controller, tambah eager loading | Tidak ada N+1 di controller yang sering diakses | P1 |
| B7.4.2 | OWASP Top 10 penetration test (manual checklist) | Semua critical fixed | P1 |
| B7.4.3 | Load test Dashboard + Kanban (50 concurrent user) | Response time < 2 detik | P2 |

---

## 4. Status per Fase

| Fase | Status | Modul Utama | # Tes | Sisa Backlog |
|---|---|---|---|---|
| **F1** Fondasi | ✅ Selesai | Auth, RBAC, Pipeline, Dashboard | 5 | 0 |
| **F2** Modul Inti | ✅ Selesai | Kanban, Pembukuan, Insight, Script, API ingest | 12 | 0 |
| **F3** SPA Migration | ✅ Selesai | Inertia + Vue 3, Content, Order | 5 | 0 |
| **F4** Kartu Lengkap | ✅ Selesai | Label, deadline, checklist, lampiran, komentar, arsip, absensi | 4 | 0 |
| **F5** Strategi & Kinerja | ✅ Selesai | OKR, KPI, Tracking, Mindmap, MCP, notifikasi | 12 | 0 |
| **F6** Automation & Audit | ⏳ Aktif | Upload YouTube, audit log, dashboard enhancement | 0 | 9 task |
| **F7** Hardening | 🔴 Belum | Security, monitoring, backup, PT | 0 | 11 task |

---

## 5. Estimasi & Tim

| Fase | Estimasi (minggu) | Roles |
|---|---|---|
| F6 | 2-3 minggu | Backend (audit log, upload), Frontend (UI upload, audit page), DevOps (VPS agent YouTube) |
| F7 | 1-2 minggu | DevOps (SSL, header, backup), Security (PT, rate limit), QA (smoke test) |
| **Total** | **3-5 minggu** | |

---

## 6. Risiko Implementasi

| Risiko | Dampak | Mitigasi | Fase |
|---|---|---|---|
| VPS agent YouTube tidak siap | Upload tertunda | Bisa deploy tanpa Upload; YouTube tetap template "soon" | F6 |
| Shared hosting tidak support custom header | Hardening parsial | Minimal: SSL + rate limit via `.htaccess` | F7 |
| Data produksi korup saat import SQL | Downtime | Backup dulu DB existing; dry-run import di staging | F6/F7 |
| User keberatan dengan session 15 menit | UX friction | Bisa dinegosiasi ke 30 menit | F7 |
| Audit log membengkak (high volume) | DB penuh | Indeks + partisi/purge setelah X bulan | F6 |
