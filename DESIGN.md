# DESIGN — Sistem Manajemen Proyek & Task

Dokumen arsitektur dan desain teknis untuk aplikasi manajemen proyek/task.

- **Stack**: Laravel 11 (PHP 8.2+), Blade + Tailwind CSS, Livewire/Alpine.js untuk interaksi, MySQL/PostgreSQL, Redis (queue & cache).
- **Tujuan**: Mengelola proyek, task, tim, deadline, dan progress secara terpusat.

---

## 1. Ruang Lingkup (Scope)

| Fitur | Deskripsi | Prioritas |
|-------|-----------|-----------|
| Autentikasi & Role | Login, register, RBAC (Admin, Manager, Member) | P0 |
| Manajemen Proyek | CRUD proyek, status, deadline, owner | P0 |
| Manajemen Task | CRUD task, assignee, prioritas, due date, subtask | P0 |
| Board Kanban | Drag & drop task antar kolom status | P1 |
| Komentar & Aktivitas | Diskusi per task + log aktivitas | P1 |
| Notifikasi | In-app + email saat assign/mention/deadline | P1 |
| Dashboard & Laporan | Ringkasan progress, beban kerja, burndown | P2 |
| Lampiran File | Upload dokumen ke task | P2 |

---

## 2. Arsitektur Aplikasi

```
┌─────────────────────────────────────────────┐
│                 Browser (SPA-lite)           │
│      Blade + Tailwind + Livewire/Alpine      │
└───────────────────────┬─────────────────────┘
                        │ HTTP / WebSocket
┌───────────────────────▼─────────────────────┐
│                Laravel Application            │
│  Routes → Controllers → Form Requests         │
│  Policies (RBAC) → Services → Actions         │
│  Eloquent Models → Repositories (opsional)    │
│  Events → Listeners → Jobs (Queue)            │
└───────────┬───────────────────────┬──────────┘
            │                       │
   ┌────────▼────────┐     ┌────────▼────────┐
   │  MySQL/Postgres │     │  Redis (Queue,  │
   │   (data utama)  │     │   Cache, Session)│
   └─────────────────┘     └─────────────────┘
```

**Pola yang dipakai:**
- **Service/Action layer** — logika bisnis dikeluarkan dari controller (mis. `CreateTaskAction`).
- **Policy-based authorization** — setiap resource punya Policy.
- **Event-driven** — perubahan penting memicu Event → Listener (notifikasi, log).
- **Queue** — email & notifikasi berat diproses async lewat Redis queue.

---

## 3. Model Data (ERD Ringkas)

Satu baris pipeline = satu entri endorsement (mengikuti sheet "PIPELINE FK-AI PRENEUR", kolom A–L sampai *NOTES*). Spesifikasi lengkap: [PRD.md](PRD.md) §4.

```
users ──< activity_logs >── pipelines ──< pipeline_output (pivot) >── outputs
                                │
                                └── (created_by / updated_by → users)
```

### Tabel Inti

**users**
| kolom | tipe | catatan |
|-------|------|---------|
| id | bigint PK | |
| name | string | |
| email | string unique | |
| password | string | hashed |
| role | enum | admin, manager, produksi |
| avatar | string nullable | |

**pipelines** — tabel utama endorsement
| kolom | tipe | header sheet | nilai |
|-------|------|--------------|-------|
| id | bigint PK | | |
| account | enum | ACCOUNT | `fk`, `ai_preneur` |
| coaching | string nullable | COACHING | |
| speaker | string nullable | SPEAKER | |
| endorse | string | ENDORSE | nama produk |
| progress | enum | PROGRESS | `editing`, `progress`, `done` |
| tanggal_posting | date nullable | TANGGAL POSTING | |
| tanggal_payment | date nullable | TANGGAL PAYMENT | |
| payment_status | enum | SUDAH/BELUM PAYMENT | `belum`, `dp`, `lunas` |
| amount_idr | decimal(15,2) nullable | JUMLAH PAYMENT IDR | |
| amount_usd | decimal(12,2) nullable | JUMLAH PAYMENT USD | |
| notes | text nullable | NOTES | |
| created_by / updated_by | FK users nullable | | audit |
| deleted_at | timestamp nullable | | soft delete |

**outputs** — master jenis output (`Youtube`, `Reels/TikTok`, `Agency`, `Foto`, `Video`)
| id | bigint PK |
| name | string |
| color | string |

**pipeline_output** (pivot) — `pipeline_id FK`, `output_id FK` (OUTPUT bisa lebih dari satu tag)

**activity_logs** — `id, user_id, subject_type, subject_id, action, changes(json), created_at`
**notifications** — tabel bawaan Laravel notifications

> Kolom `coaching` & `speaker` untuk sekarang hanya teks (lihat Non-Goals di PRD §8).

---

## 4. Struktur Direktori (Laravel)

```
app/
├── Actions/           # CreateTaskAction, MoveTaskAction, ...
├── Http/
│   ├── Controllers/   # ProjectController, TaskController, ...
│   ├── Requests/      # StoreTaskRequest, UpdateProjectRequest
│   └── Middleware/
├── Models/            # User, Project, Task, Comment, ...
├── Policies/          # ProjectPolicy, TaskPolicy
├── Services/          # NotificationService, ReportService
├── Events/ Listeners/ # TaskAssigned → SendAssignmentNotification
└── Livewire/          # KanbanBoard, TaskModal, ...
resources/
├── views/             # Blade + Tailwind
├── css/ js/           # Tailwind entry, Alpine
database/
├── migrations/ factories/ seeders/
tests/
├── Feature/ Unit/
```

---

## 5. Rute Utama (contoh)

```php
// Proyek
GET    /projects                 index
POST   /projects                 store
GET    /projects/{project}       show (board)
PUT    /projects/{project}       update

// Task
POST   /projects/{project}/tasks store
PUT    /tasks/{task}             update
PATCH  /tasks/{task}/move        pindah kolom (kanban)
DELETE /tasks/{task}             destroy

// Komentar
POST   /tasks/{task}/comments    store
```

---

## 6. Otorisasi (RBAC)

| Aksi | Admin | Manager | Member (project) | Viewer |
|------|:-----:|:-------:|:----------------:|:------:|
| Kelola user | ✅ | ❌ | ❌ | ❌ |
| Buat proyek | ✅ | ✅ | ❌ | ❌ |
| Edit proyek | ✅ | ✅ (miliknya) | ❌ | ❌ |
| Buat/edit task | ✅ | ✅ | ✅ | ❌ |
| Komentar | ✅ | ✅ | ✅ | ❌ |
| Lihat proyek | ✅ | ✅ | ✅ | ✅ |

Diterapkan via **Policies** + Gate, dicek di controller (`$this->authorize()`) dan view (`@can`).

---

## 7. Keputusan Teknis

- **Livewire** dipilih untuk interaktivitas (kanban, modal, komentar realtime) agar tetap dalam ekosistem Laravel tanpa SPA terpisah.
- **Tailwind** untuk styling utility-first; komponen UI reusable via Blade components.
- **Soft deletes** pada projects & tasks untuk recovery.
- **Queue (Redis)** untuk notifikasi & email agar request tetap cepat.
- **Broadcasting (Laravel Echo + Pusher/Reverb)** — opsional untuk update board realtime.

---

## 8. Rencana Rilis (Milestone)

1. **M1 — Fondasi**: auth, RBAC, CRUD proyek & task, seeding.
2. **M2 — Kolaborasi**: kanban board, komentar, aktivitas, notifikasi.
3. **M3 — Insight**: dashboard, laporan, lampiran, filter & search.
4. **M4 — Polish**: realtime, mobile-responsive, ekspor, hardening.

> Detail kapabilitas per peran ada di [SKILLS.md](SKILLS.md).
> Peran/agent pembangun sistem ada di [AGENTS.md](AGENTS.md).
