# DESIGN — System AI Preneur

Dokumen arsitektur & desain teknis. Produk: lihat [PRD.md](PRD.md).

- **Stack**: Laravel 13 (PHP 8.5), **Inertia.js + Vue 3** (SPA), Tailwind v4, Vite. SQLite (mode WAL). PDF via DomPDF. Grafik via Chart.js/vue-chartjs (modul Pembukuan).
- **Tujuan**: mengelola pipeline endorsement, alur produksi Kanban, pembukuan, script, dan user secara terpusat dengan hak akses per peran.

---

## 1. Arsitektur

```
┌───────────────────────────────────────────────┐
│                 Browser (SPA)                  │
│   Vue 3 (resources/js/Pages/*.vue) + Inertia   │
│            Tailwind v4 · Chart.js              │
└───────────────────────┬───────────────────────┘
                        │ Inertia (XHR + JSON props)
┌───────────────────────▼───────────────────────┐
│                Laravel Application             │
│  Routes (web) → Middleware(auth,               │
│     EnsureMenuAccess, HandleInertiaRequests)   │
│  Controllers → Inertia::render(Page, props)    │
│  Eloquent Models → SQLite                       │
│  DomPDF (report) · ExchangeRate (kurs)          │
└────────────────────────────────────────────────┘
```

**Pola yang dipakai (nyata, tanpa over-engineering):**
- **Inertia** — controller mengembalikan `Inertia::render('Page', $props)`; Vue merender tanpa REST terpisah. Redirect Laravel biasa (`->back()`) memicu reload props.
- **Otorisasi dua lapis** di middleware `EnsureMenuAccess`: (1) akses menu per peran, (2) route mutasi butuh `canManage()`. Bukan Policy per-model.
- **Shared props** via `HandleInertiaRequests`: `auth.user` (id, role, canManage, peta menu) & `flash.status`.
- **Fetch langsung** (non-Inertia) hanya untuk aksi drag-drop & todo (PATCH JSON, optimistic UI).

> Tidak memakai queue, events/listeners, Policies, Actions/Services layer, atau broadcasting — sengaja dijaga sederhana.

---

## 2. Model Data

```
users ──< pipelines (assigned_to, created_by, key_result_id)
categories (board) 1──n board_columns (kolom kanban, key=progress)
objectives 1──n key_results 1──n pipelines (key_result_id, nullable)
pipelines ──< output_pipeline (pivot) >── outputs
pipelines 1──n pipeline_comments >── users
pipelines 1──n pipeline_attachments >── users
transactions · inventories        (Pembukuan, berdiri sendiri)
insight_accounts · insight_contents (Insight — sumber realisasi OKR)
```

> **Skema lengkap semua tabel & aturan kolom** ada di [Schema.md](Schema.md). Di bawah hanya tabel inti + keputusan desainnya.

### Tabel inti

**users** — `id, name, email, password, role`
Peran: `owner, manager, it, admin, staff`. Method domain: `canSee($menu)`, `canManage()`, `canManageBoard($cat)`, `homeRoute()`.

**pipelines** — kartu/entri utama
`account`(fk/ai_preneur), `assigned_to`(FK users), `endorse`, `description`, `progress`(= key kolom board, string dinamis), `category`(= key board), `jenis`(endorse/coaching_1on1/coaching_perusahaan/agensi/speaker — dulu board terpisah, kini atribut kartu; null utk kartu kanban), `tanggal_posting/payment`, `deadline`, `payment_status`(belum/dp/lunas), `amount_idr/usd`, `notes`, `link`, `todos`(json), `labels`(json), `ke_gilang`, `catatan`, `archived_at`, `created_by/updated_by`, `deleted_at`(soft delete).

**categories** — board dinamis: `key`, `name`, `type`(pipeline/kanban). Board `pipeline` hanya satu: `sales`.
**board_columns** — kolom kanban per board: `board_key`, `key`, `name`, `color`, `position` (unik `[board_key,key]`).
**outputs** + **output_pipeline** (pivot) — tag output multi.
**pipeline_comments** — `pipeline_id, user_id, body`.
**pipeline_attachments** — `pipeline_id, user_id, path, name, mime, size` (disk `public`).
**transactions** — `type`(pemasukan/pengeluaran), `category`, `amount_idr`, `date`.
**inventories** — `name, qty, unit_value_idr, month`.

**objectives / key_results** — OKR kuartalan. `key_results.source` ∈ `auto`(dari Insight/Pembukuan) · `manual` · `kartu`(dari kartu todolist tertaut). Realisasi **tak disimpan** — dihitung saat render.

> `progress`, `category`, `source` sengaja string (bukan enum) agar dinamis / mudah bertambah tanpa migrasi. Kartu dgn kolom terhapus jatuh ke kolom pertama.

### Aliran OKR → eksekusi

```
Objective (goal kuartal)
   └─ Key Result (terukur)
        source=kartu ──┐
                       ▼
         pipelines.key_result_id  (kartu Kanban todolist)
                       │  completed_at terisi?
                       ▼
         realisasi KR = jumlah kartu tertaut yang selesai
```

Owner menulis goal di `/okr`; langkah pencapaian dibuat sebagai kartu di Kanban **todolist** dan ditautkan ke KR (`pipelines.key_result_id`). Menyelesaikan kartu (masuk kolom terakhir → `completed_at`) menggerakkan angka KR otomatis. Rumus realisasi ada satu tempat: `KeyResult::actual()` / `OkrMetrics::realisasi()`; ketepatan waktu di `Pipeline::ketepatan()`; keduanya dipakai ulang, tak disalin.

---

## 3. Struktur Direktori

```
app/
├── Http/Controllers/     # Pipeline, Board, Column, Comment, Attachment,
│                         # Okr, Kpi, Insight, Dashboard, Pembukuan, Order,
│                         # Content, Tracking, Absence, Akses, User, Auth
├── Http/Middleware/       # EnsureMenuAccess, HandleInertiaRequests
├── Models/                # Pipeline, Category, BoardColumn, Output, Label,
│                         # Objective, KeyResult, BoardQuarterTarget,
│                         # Insight*, Transaction, Inventory, User, …
└── Support/               # ExchangeRate (kurs), Quarter (tanggal↔kuartal),
                          # OkrMetrics (realisasi auto), KinerjaOrang (rapor)
resources/
├── js/
│   ├── app.js             # entry Inertia (Vue)
│   ├── Layout.vue  Sidebar.vue  ModalWrap.vue
│   ├── Pages/             # Login, Dashboard, Kanban (dipakai /pipelines &
│   │                     # /pipelines/kanban), Pembukuan, Script, Users
│   └── scripts/components # komponen Chart.js pembukuan
├── css/app.css            # Tailwind v4 + palet brand + safelist warna dinamis
└── views/                 # app.blade.php (root Inertia) + *.report (PDF)
database/migrations · seeders
```

---

## 4. Rute Utama

```php
// Halaman (Inertia::render)
GET  /dashboard  /pipelines  /pipelines/kanban  /script  /pembukuan  /users

// Pipeline / kartu
POST/PUT/DELETE /pipelines[/{id}]
PATCH /pipelines/{id}/progress   // drag-drop (JSON)
PATCH /pipelines/{id}/todos      // checklist (JSON)
PATCH /pipelines/{id}/archive    // arsip (manage)

// Board & kolom dinamis (manage)
POST/PUT/DELETE /boards[/{board}]
POST/PUT/DELETE /columns[/{column}]

// Kolaborasi
POST   /pipelines/{id}/comments      // semua peran kanban (incl. staff ditugasi)
DELETE /comments/{comment}           // penulis atau manager
POST   /pipelines/{id}/attachments   // manage
DELETE /attachments/{attachment}     // manage

// Report PDF
GET /pembukuan/report
```

---

## 5. Otorisasi (per peran, bukan per-model)

`EnsureMenuAccess` mengecek tiap request:
1. **Route mutasi** (store/update/destroy/archive/board/column/attachment/okr.*/kpi.targets.*) → butuh `canManage()`. Komentar **dikecualikan** agar staff yang ditugasi bisa berkomentar.
2. **Akses menu** → route dipetakan ke menu; user harus `canSee()` menu itu.

**Penyaringan data juga di server, bukan cuma menyembunyikan di Vue.** Props Inertia terbaca di source halaman, jadi:
- Panel KPI di Kanban (`quarterStats`) hanya dikirim bila `canManage()` — query-nya pun tak dijalankan untuk staff.
- Rapor KPI per orang: peran non-manajemen hanya menerima barisnya sendiri; nama & angka rekan kerja tak ikut terkirim.

UI menyembunyikan aksi terlarang; server tetap membalas **403** (atau mengirim `null`) bila dilanggar.

---

## 6. Keputusan Teknis

- **Inertia + Vue** menggantikan Blade+Alpine — satu SPA, komponen per halaman, tanpa REST API terpisah. (Vue dipilih ketimbang React karena preferensi tim untuk produksi shared hosting; keduanya sama-sama di-build lokal.)
- **SQLite (WAL)** untuk dev; deploy via import file `.sql`. `busy_timeout` & `synchronous=NORMAL` diset di `config/database.php`.
- **Progress = key kolom** — reuse kolom `progress` sebagai referensi kolom dinamis (hindari FK column_id + backfill).
- **Safelist Tailwind** (`@source inline(...)`) untuk warna kolom/label yang datang dari DB (tak terbaca scanner).
- **Soft delete** pada pipeline untuk recovery.

> Detail kapabilitas per peran: [SKILLS.md](SKILLS.md). Peran pembangun: [AGENTS.md](AGENTS.md).
