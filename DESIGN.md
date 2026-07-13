# DESIGN — System AI Preneur

Dokumen arsitektur & desain teknis. Produk: lihat [PRD.md](PRD.md).

- **Stack**: Laravel 13 (PHP 8.5), **Inertia.js + React 19** (SPA), Tailwind v4, Vite. SQLite (mode WAL). PDF via DomPDF. Grafik via Chart.js (modul Pembukuan).
- **Tujuan**: mengelola pipeline endorsement, alur produksi Kanban, pembukuan, script, dan user secara terpusat dengan hak akses per peran.

---

## 1. Arsitektur

```
┌───────────────────────────────────────────────┐
│                 Browser (SPA)                  │
│   React 19 (resources/js/Pages/*) + Inertia    │
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
- **Inertia** — controller mengembalikan `Inertia::render('Page', $props)`; React merender tanpa REST terpisah. Redirect Laravel biasa (`->back()`) memicu reload props.
- **Otorisasi dua lapis** di middleware `EnsureMenuAccess`: (1) akses menu per peran, (2) route mutasi butuh `canManage()`. Bukan Policy per-model.
- **Shared props** via `HandleInertiaRequests`: `auth.user` (id, role, canManage, peta menu) & `flash.status`.
- **Fetch langsung** (non-Inertia) hanya untuk aksi drag-drop & todo (PATCH JSON, optimistic UI).

> Tidak memakai queue, events/listeners, Policies, Actions/Services layer, atau broadcasting — sengaja dijaga sederhana.

---

## 2. Model Data

```
users ──< pipelines (assigned_to, created_by/updated_by)
categories (board) 1──n board_columns (kolom kanban, key=progress)
pipelines ──< pipeline_output (pivot) >── outputs
pipelines 1──n pipeline_comments >── users
pipelines 1──n pipeline_attachments >── users
transactions · inventories        (modul pembukuan, berdiri sendiri)
```

### Tabel inti

**users** — `id, name, email, password, role`
Peran: `super_admin, it, admin, editor, staff`. Method domain: `canSee($menu)`, `canManage()`, `homeRoute()`.

**pipelines** — kartu/entri utama
`account`(fk/ai_preneur), `assigned_to`(FK users), `endorse`, `description`, `progress`(= key kolom board, string dinamis), `category`(= key board), `tanggal_posting/payment`, `deadline`, `payment_status`(belum/dp/lunas), `amount_idr/usd`, `notes`, `link`, `todos`(json), `labels`(json), `ke_gilang`, `catatan`, `archived_at`, `created_by/updated_by`, `deleted_at`(soft delete).

**categories** — board dinamis: `key`, `name`.
**board_columns** — kolom kanban per board: `board_key`, `key`, `name`, `color`, `position` (unik `[board_key,key]`).
**outputs** + **output_pipeline** (pivot) — tag output multi.
**pipeline_comments** — `pipeline_id, user_id, body`.
**pipeline_attachments** — `pipeline_id, user_id, path, name, mime, size` (disk `public`).
**transactions** — `type`(pemasukan/pengeluaran), `category`, `amount_idr`, `date`.
**inventories** — `name, qty, unit_value_idr, month`.

> `progress` & `category` sengaja string (bukan enum) agar board/kolom dinamis. Kartu dgn kolom terhapus jatuh ke kolom pertama.

---

## 3. Struktur Direktori

```
app/
├── Http/Controllers/     # Pipeline, Board, Column, Comment, Attachment,
│                         # Dashboard, Pembukuan, User, Auth
├── Http/Middleware/       # EnsureMenuAccess, HandleInertiaRequests
├── Models/                # Pipeline, Category, BoardColumn, Output,
│                         # PipelineComment, PipelineAttachment,
│                         # Transaction, Inventory, User
└── Support/               # ExchangeRate (kurs USD→IDR, cache 12 jam)
resources/
├── js/
│   ├── app.jsx            # entry Inertia
│   ├── Layout.jsx  Sidebar.jsx
│   ├── Pages/             # Login, Dashboard, Kanban, Pipelines/Index,
│   │                     # Pembukuan, Script, Users
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
GET /pipelines/report   /pembukuan/report
```

---

## 5. Otorisasi (per peran, bukan per-model)

`EnsureMenuAccess` mengecek tiap request:
1. **Route mutasi** (store/update/destroy/archive/board/column/attachment) → butuh `canManage()` (super_admin/it). Komentar **dikecualikan** agar staff/editor bisa berkomentar.
2. **Akses menu** → route dipetakan ke menu (dashboard/pipeline/kanban/script/pembukuan/user); user harus `canSee()` menu itu.

UI menyembunyikan aksi terlarang; server tetap membalas **403** bila dilanggar.

---

## 6. Keputusan Teknis

- **Inertia + React** menggantikan Blade+Alpine — satu SPA, komponen per halaman, tanpa REST API terpisah.
- **SQLite (WAL)** untuk dev; deploy via import file `.sql`. `busy_timeout` & `synchronous=NORMAL` diset di `config/database.php`.
- **Progress = key kolom** — reuse kolom `progress` sebagai referensi kolom dinamis (hindari FK column_id + backfill).
- **Safelist Tailwind** (`@source inline(...)`) untuk warna kolom/label yang datang dari DB (tak terbaca scanner).
- **Soft delete** pada pipeline untuk recovery.

> Detail kapabilitas per peran: [SKILLS.md](SKILLS.md). Peran pembangun: [AGENTS.md](AGENTS.md).
