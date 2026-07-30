# O1-Arsitektur Sistem — Sistem AI Preneur

Dokumen arsitektur teknis, data model, auth/role/audit, API standard, dan keamanan. PRD: [O1-PRD-MODULAR.md](O1-PRD-MODULAR.md). Konvensi: [AGENTS.md](AGENTS.md).

---

## 1. Arsitektur Sistem

```
┌───────────────────────────────────────────────────────┐
│                   Browser (SPA)                        │
│   Inertia.js + Vue 3 + Tailwind v4 + Chart.js          │
│   resources/js/Pages/*.vue                             │
└───────────────────────┬───────────────────────────────┘
                        │ Inertia (XHR + JSON props)
┌───────────────────────▼───────────────────────────────┐
│                Laravel 13 Application                  │
│                                                        │
│  routes/web.php (session + CSRF)                       │
│  ├─ Middleware: auth                                   │
│  ├─ Middleware: EnsureMenuAccess (otorisasi)           │
│  ├─ Middleware: HandleInertiaRequests (shared props)   │
│  │                                                      │
│  Controllers → Inertia::render('Page', $props)         │
│                → JSON (drag-drop, todo, notif)         │
│                → DomPDF (report PDF)                   │
│  Models: Eloquent ORM (21 model)                       │
│  Support: ExchangeRate, Quarter, OkrMetrics,            │
│           KinerjaOrang, OkrNotifications               │
└───────────────────────┬───────────────────────────────┘
                        │
┌───────────────────────▼───────────────────────────────┐
│  routes/api.php (bearer token, no session)             │
│  ├─ POST /api/scripts  → ScriptIngestController        │
│  └─ POST /api/insights → InsightIngestController       │
│  Middleware: throttle:30,1 + token auth                │
└───────────────────────┬───────────────────────────────┘
                        │
┌───────────────────────▼───────────────────────────────┐
│                 Database (MariaDB / SQLite)             │
│  65+ tabel: users, pipelines, categories, board_columns│
│  objectives, key_results, transactions, inventories    │
│  insight_accounts, insight_contents, scripts, ...      │
└───────────────────────────────────────────────────────┘
                        ▲
┌───────────────────────┴───────────────────────────────┐
│  Node.js MCP Server (/mcp-server/)                     │
│  4 tool: list_okr, create_objective,                   │
│          create_key_result, link_task_to_kr            │
│  → Mengakses database SQLite yang sama                  │
└───────────────────────────────────────────────────────┘
```

### Pola komunikasi

| Jalur | Protokol | Auth | Dipakai untuk |
|---|---|---|---|
| **Inertia** | XHR + JSON props | Session + CSRF | Semua halaman & form |
| **JSON endpoint** | HTTP PATCH/POST | Session + CSRF | Drag-drop, todo, arsip, notif |
| **API** | HTTP POST | Bearer token | Ingest data dari VPS |
| **MCP** | stdio JSON-RPC | Local filesystem | AI tool akses OKR |
| **PDF** | HTTP GET | Session + CSRF | Report (DomPDF) |

---

## 2. Data Model

### ERD Ringkas

```
users ─┬─< pipelines            (assigned_to · created_by)
       ├─< objectives           (created_by)
       ├─< key_results          (owner_id · created_by)
       ├─< notifications        (notifiable morph → User)
       ├─< board_quarter_targets(created_by)
       ├─< pipeline_comments    (user_id)
       ├─< pipeline_attachments (user_id)
       ├─< absences             (user_id)
       └─< mindmaps             (user_id)

categories(board) 1──n board_columns
        │                    │
        │ board_key          │ key
        ▼                    ▼
pipelines.category    pipelines.progress

objectives 1──n key_results 1──n pipelines (key_result_id, nullable)

pipelines n──n outputs (pivot output_pipeline)
pipelines 1──n pipeline_comments
pipelines 1──n pipeline_attachments

transactions      {Pembukuan — berdiri sendiri}
inventories       {Pembukuan — berdiri sendiri}

insight_accounts  {Insight — sumber realisasi OKR}
insight_contents  {Insight — sumber realisasi OKR}

scripts · contents · orders · order_output
role_menu_access · labels · notifications
```

### Prinsip schema

| Prinsip | Detail |
|---|---|
| **String bukan ENUM** | `progress`, `category`, `source`, `jenis` semua `varchar`. Daftar sah dijaga di validasi controller + konstanta model. |
| **Realisasi dihitung, bukan disimpan** | Realisasi OKR auto/kartu tidak punya kolom — dihitung saat render dari Insight, Transactions, dan Pipelines (`KeyResult::actual()`). |
| **Soft delete** | Pipeline (restorable). FK `nullOnDelete` untuk `created_by`, `owner_id`, `assigned_to`. `cascadeOnDelete` untuk children yang tak berarti tanpa parent. |
| **JSON untuk data fleksibel** | `pipelines.labels`, `pipelines.todos`, `objectives.priority`, `key_results.priority` — snapshot, bukan FK. |
| **Satu tabel untuk Pipeline + Kanban** | Tabel `pipelines` dipakai bersama, dibedakan `categories.type` (pipeline/kanban). |

### Indeks kritis

| Tabel | Indeks | Alasan |
|---|---|---|
| `pipelines` | `(category, progress)` | Query board + kolom |
| `pipelines` | `(assigned_to, completed_at)` | Rapor KPI per orang |
| `pipelines` | `(deadline)` | Kuartal kartu & ketepatan |
| `pipelines` | `(key_result_id, completed_at)` | Realisasi KR kartu |
| `objectives` | `(year, quarter)` | Query OKR kuartalan |
| `transactions` | `(date)` | Pembukuan bulanan |
| `insight_contents` | `(platform, content_id)` unik | Upsert idempoten |

---

## 3. Auth & Role

### Peran & kemampuan

| Peran | Menu | Mutasi | Khusus |
|---|---|---|---|
| **owner** | Semua | ✅ penuh | Imun: `canSee()` selalu true |
| **it** | Semua (teknis) | ✅ penuh | Kelola User & Akses |
| **manager** | Operasional + strategi | ✅ board, task, target | OKR, Pembukuan, Tracking |
| **admin** | Pipeline, Kanban, dll | ✅ kartu saja | Bukan struktur board |
| **staff** | Kanban, Mindmap, KPI | ❌ (kartu kanban saja) | Rapor KPI sendiri |

### Mekanisme otorisasi (dua lapis)

```
Request HTTP
  │
  ├─ auth middleware → session valid?
  │
  ├─ EnsureMenuAccess middleware:
  │    ├─ Route = mutasi? → cek User::canManage()
  │    ├─ Route = menu page? → cek User::canSee($menu)
  │    └─ Komentar? → pengecualian (staff boleh komentar)
  │
  └─ Controller: saring data sesuai peran SEBELUM kirim props
       (jangan andalkan v-if saja)
```

### Aturan kunci

1. **OKR, Pembukuan, Tracking = hard lock** di `User::canSee()`. Tidak bisa dibuka lewat `role_menu_access`.
2. **KPI rapor per orang**: server hanya kirim baris user yang login (kecuali owner/manager).
3. **Panel KPI di Kanban** (`quarterStats`): query tidak dijalankan untuk staff.
4. **Props Inertia = public di source**. Data sensitif jangan pernah masuk props untuk peran yang tak berhak.

### Skema tabel akses

```
users: id, name, email, password, role (varchar)

role_menu_access:
  role   (varchar) ─┐
  menu   (varchar) ─┤ unik bersama
  can_manage (bool) ─┘
```

---

## 4. Audit Log

### Kebutuhan

| # | Kebutuhan | Detail |
|---|---|---|
| A1 | Catat semua aksi mutasi | Create, update, delete, arsip/restore, drag progress, approve/reject |
| A2 | Identitas aktor | `user_id` + `role` saat aksi |
| A3 | Target entity | Model + ID + representasi ringkas (nama/judul) |
| A4 | Nilai sebelum & sesudah | Untuk update: field yang berubah, old_value, new_value (JSON) |
| A5 | Timestamp | `created_at` presisi |
| A6 | Filterable & searchable | Halaman UI dengan filter: user, aksi, model, rentang tanggal |
| A7 | Immutable | Tidak bisa diedit, hanya dibaca |

### Desain tabel `audit_logs`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint auto-increment | PK |
| `user_id` | FK users nullable | Aktor; null untuk sistem/cron |
| `user_role` | varchar | Snapshot peran saat aksi |
| `action` | varchar | create / update / delete / archive / restore / progress / approve / reject |
| `model_type` | varchar | Nama model: Pipeline, BoardColumn, Objective, dll |
| `model_id` | bigint | ID entity |
| `model_name` | varchar | Judul/representasi entity (untuk pencarian) |
| `old_values` | json nullable | Field & nilai sebelum |
| `new_values` | json nullable | Field & nilai setelah |
| `ip_address` | varchar nullable | IP aktor |
| `user_agent` | varchar nullable | Browser / client |
| `created_at` | timestamp | Kapan aksi terjadi |

### Strategi implementasi

```php
// Trait yang dipasang di model yang perlu diaudit
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn($model) => AuditLog::record('create', $model));
        static::updated(fn($model) => AuditLog::record('update', $model));
        static::deleted(fn($model) => AuditLog::record('delete', $model));
    }
}

// Facade static
AuditLog::record($action, $model, $oldValues = null, $newValues = null);
```

### Halaman audit log

- Route: `/audit` (owner + it saja).
- UI: tabel dengan filter (user, aksi, model, rentang tanggal) + search judul entity.
- Tidak bisa CRUD — read only.
- Pagination 50 baris/halaman.

### Cakupan audit

| Model | Aksi yang direkam |
|---|---|
| Pipeline | create, update, delete, archive, restore, progress (drag) |
| BoardColumn | create, update, delete |
| Category | create, update, delete |
| Objective | create, update, delete |
| KeyResult | create, update, delete |
| BoardQuarterTarget | create, update, delete |
| Transaction | create, update, delete |
| Inventory | create, update, delete |
| User | create, update, delete |
| Absence | approve, reject |
| Content, Order, Script | create, update, delete |

---

## 5. API Standard

### API endpoints (ada)

| Method | Route | Auth | Rate Limit | Idempotensi |
|---|---|---|---|---|
| `POST` | `/api/scripts` | Bearer `config('services.script_agent.token')` | 30/min | Replace batch per `brand`+`generated_for` |
| `POST` | `/api/insights` | Bearer `config('services.insight_agent.token')` | 30/min | Upsert per `platform`+`content_id`/`akun`+`tanggal` |

### API conventions

#### Auth
- Semua API endpoint memakai **Bearer token** di header `Authorization`.
- Timing-safe comparison (`hash_equals`).
- Token kosong → **503 Service Unavailable** (belum dikonfigurasi).
- Token salah → **401 Unauthorized**.

#### Rate limiting
- Semua API endpoint: `throttle:30,1` (30 request per menit).
- Jika limit terlampaui → **429 Too Many Requests**.

#### Response format
```json
// Sukses
{
  "message": "string",
  "processed": { "contents": 150, "accounts": 12 }
}

// Error validasi
{
  "message": "The given data was invalid.",
  "errors": { "field": ["Pesan error"] }
}

// Error umum
{
  "message": "Token tidak sah."
}
```

#### Idempotensi
- **Insight**: `updateOrCreate()` berdasarkan key unik. Cron re-fetch data yang sama → aman.
- **Script**: Hapus batch lama (`brand` + `generated_for`), insert baru. Re-run GitHub Actions → aman.

#### Keamanan
- Semua request API dibungkus `DB::transaction()`.
- Validasi ketat: max items per batch (500 konten, 100 akun, 100 script).
- `max:250` karakter untuk string; `integer|min:0` untuk angka metrik.
- Tanggal wajib `date`; tidak ada eksekusi kode dari input.

---

## 6. Keamanan

### 6.1 Vektor ancaman & mitigasi

| # | Ancaman | Mitigasi | Status |
|---|---|---|---|
| S1 | **Broken Access Control** — user melihat/mengubah data di luar perannya | Middleware `EnsureMenuAccess` + penyaringan data di server (props tak dikirim) | ✅ |
| S2 | **Insecure Direct Object Reference** — user mengakses ID yang bukan miliknya | Cek `canManageBoard()` untuk kartu; rapor KPI disaring server | ✅ |
| S3 | **Mass Assignment** — field sensitif diisi via POST | `$fillable` ketat di semua model | ✅ |
| S4 | **XSS** — input user dirender mentah | Vue auto-escape `{{ }}` + validasi server | ✅ |
| S5 | **CSRF** — request jahat dari situs lain | Laravel CSRF token di semua form Inertia + `X-XSRF-TOKEN` | ✅ |
| S6 | **SQL Injection** | Eloquent parameter binding; tidak ada raw query dengan input user | ✅ |
| S7 | **File Upload** — shell injection via upload | Validasi MIME, max 10MB, disk `public` non-executable | ✅ |
| S8 | **API Token Leak** | Token disimpan di `.env` (tidak di repo); `hash_equals` untuk perbandingan | ✅ |
| S9 | **Sensitive Data Exposure** — props Inertia terlihat di source | Data tak dikirim ke peran yang tak berhak (bukan cuma `v-if`) | ✅ |
| S10 | **Hardcoded Secrets** | Semua secret di `.env`; `config()` untuk akses | ✅ |
| S11 | **Race Condition** — concurrent update kartu | `completed_at` stempel pertama dipertahankan; `updateOrCreate` idempoten | ✅ |
| S12 | **Denial of Service** — API spam | Rate limit 30/min + max batch size validasi | ✅ |
| S13 | **Audit Trail Tampering** | Audit log immutable (read-only API, no update/delete) | ⏳ direncanakan |
| S14 | **Session Hijacking** | HttpOnly cookie, session expire, `same_site: lax` | ✅ |
| S15 | **Open Registration Abuse** | Register langsung staff (by design); tidak ada elevated privilege dari register | ✅ |

### 6.2 Best practices yang sudah diterapkan

- **Server-side rendering props**: Data yang tak boleh dilihat peran tertentu tidak dikirim dari controller (mis. `quarterStats` null untuk staff, rapor KPI cuma baris sendiri).
- **Hard lock menu sensitif**: `okr`, `pembukuan`, `tracking` dikunci di kode (`User::canSee()`), tidak bisa dibuka lewat halaman Akses.
- **Imunitas owner**: `canSee()` selalu true — owner tidak pernah terkunci.
- **Validasi pilihan di server**: Batasan seperti label maks 1, bukan cuma di UI picker.
- **Stempel `completed_at` freeze**: Stempel pertama dipertahankan — kartu telat tidak bisa "dirapikan".

### 6.3 Hardening yang direkomendasikan (Fase 7)

| # | Item | Prioritas |
|---|---|---|
| H1 | HTTPS enforcement (server) | P0 |
| H2 | Security headers: CSP, HSTS, X-Frame-Options | P0 |
| H3 | Session timeout 15 menit (config `session.lifetime`) | P1 |
| H4 | Rate limiting untuk route auth (login, register) | P1 |
| H5 | Log monitoring: kirim log level `error` ke Slack | P1 |
| H6 | Audit log (sudah dalam roadmap) | P1 |
| H7 | Backup database otomatis (cron dump) | P1 |
| H8 | File upload: scan malware (VPS agent) | P2 |
| H9 | Penetration testing | P2 |

---

## 7. Struktur Direktori

```
pipeline/
├── app/
│   ├── Http/Controllers/
│   │   ├── PipelineController.php      (Sales + Kanban CRUD)
│   │   ├── BoardController.php         (Category CRUD)
│   │   ├── ColumnController.php        (BoardColumn CRUD)
│   │   ├── CommentController.php       (Komentar kartu)
│   │   ├── AttachmentController.php    (Lampiran)
│   │   ├── OkrController.php           (OKR CRUD + notif)
│   │   ├── KpiController.php           (KPI board + orang)
│   │   ├── DashboardController.php     (Ringkasan)
│   │   ├── PembukuanController.php     (Transaksi + grafik)
│   │   ├── InsightController.php       (Tampilan insight)
│   │   ├── ScriptController.php        (Tampilan script)
│   │   ├── OrderController.php         (Order CRUD)
│   │   ├── MindmapController.php       (Galeri + editor)
│   │   ├── ContentController.php       (Content CRUD)
│   │   ├── TrackingController.php      (Ringkasan eksekutif)
│   │   ├── AbsenceController.php       (Cuti/sakit)
│   │   ├── UploadController.php        (Upload — TEMPLATE)
│   │   ├── UserController.php          (User CRUD)
│   │   ├── AksesController.php         (Role menu access)
│   │   ├── AuthController.php          (Login/Register)
│   │   ├── NotificationController.php  (Notif CRUD)
│   │   ├── LabelController.php         (Label CRUD)
│   │   ├── TransactionController.php   (Transaksi CRUD)
│   │   ├── InventoryController.php     (Inventaris CRUD)
│   │   ├── PipelineTaskController.php  (Todo/checklist)
│   │   └── Api/
│   │       ├── InsightIngestController.php
│   │       └── ScriptIngestController.php
│   ├── Http/Middleware/
│   │   ├── EnsureMenuAccess.php
│   │   └── HandleInertiaRequests.php
│   ├── Models/ (21 model)
│   └── Support/
│       ├── ExchangeRate.php
│       ├── Quarter.php
│       ├── OkrMetrics.php
│       ├── KinerjaOrang.php
│       └── OkrNotifications.php
├── resources/
│   ├── js/
│   │   ├── Pages/ (20 .vue halaman)
│   │   ├── Layout.vue
│   │   ├── Sidebar.vue
│   │   ├── ModalWrap.vue
│   │   └── scripts/
│   ├── css/app.css
│   └── views/ (app.blade.php + PDF reports)
├── database/migrations/ (65+ file)
├── routes/
│   ├── web.php (194 baris)
│   └── api.php (20 baris)
├── mcp-server/
├── docs/ (dokumentasi)
└── tests/ (29 file, 275 tes)
```

---

## 8. Keputusan Teknis

| # | Keputusan | Alasan | Implikasi |
|---|---|---|---|
| T1 | Vue (bukan React) via Inertia | Tim memilih Vue; keduanya sama-sama di-build lokal | SPA tanpa REST terpisah |
| T2 | SQLite WAL untuk dev; MariaDB produksi | Dev cepat tanpa daemon; deploy import .sql | Migrasi harus bekerja di dua DB |
| T3 | Progress = key kolom (string) | Hindari FK column_id + backfill | Kolom dihapus → kartu jatuh ke kolom pertama |
| T4 | Tidak pakai Policy per-model | Middleware + method User cukup untuk otorisasi role-based | Tidak ada granular per-record ACL di luar board/key_result |
| T5 | Tidak ada queue/events | Tanpa daemon di shared hosting | Semua operasi sinkron; batch API kecil |
| T6 | Upload via VPS agent | Laravel tidak panggil API eksternal | Butuh VPS running untuk upload aktif |
| T7 | Soft delete (Pipeline) | Recovery tanpa restore DB | Query selalu scope non-deleted kecuali explicit |
| T8 | completed_at flag untuk "selesai" | Satu sumber kebenaran; dihitung di banyak tempat | Semua analitik pakai `completed_at` |
