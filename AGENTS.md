# AGENTS — Sistem AI Preneur

Dokumen ini mendefinisikan **sistem AI Preneur** dan **agent/peran** (manusia maupun AI-assisted) yang membangun serta memeliharanya.

**AI Preneur** adalah sistem manajemen bisnis endorsement/konten untuk ekosistem FK-AI Preneur, terdiri dari 4 modul: **Dashboard**, **Kanban**, **Pembukuan**, dan **Pipeline**.

Referensi: [DESIGN.md](DESIGN.md) · [SKILLS.md](SKILLS.md)

> Format ini juga kompatibel sebagai `AGENTS.md` — dibaca oleh coding agent (Claude Code dsb.) untuk memahami konvensi proyek.

---

## Modul Sistem

| Modul | Route/Menu | Status | Deskripsi |
|-------|-----------|--------|-----------|
| **Dashboard** | `pipelines.index` (menu "Dashboard") | ✅ berjalan | Tabel entri + summary omzet (IDR/USD, kurs), filter kategori/account/progress/payment, CRUD entri via modal. |
| **Kanban** | `pipelines.kanban` | ✅ berjalan | Board per kolom Progress (Script → Editing → Progress → Pending → Done). Drag-drop ubah status, tambah task per kolom, filter cards. |
| **Pembukuan** | *(belum)* | 🔜 rencana | Pencatatan keuangan: pemasukan/pengeluaran, laporan omzet, rekap per periode/account. Kemungkinan pakai data `Pipeline.amount_idr/usd` sebagai sumber. |
| **Pipeline** | model `Pipeline` | ✅ inti data | Entitas utama: entri endorsement (account, endorse, outputs, progress, payment, tanggal, jumlah). Dipakai lintas modul di atas. |

**Konsep lintas modul:**
- **Kategori** (`Pipeline::CATEGORIES`): endorse, agensi, coaching, speaker — berfungsi sebagai "board".
- **Kurs USD→IDR**: via `App\Support\ExchangeRate` (ada fallback bila API gagal).
- **Auth**: seluruh modul di belakang middleware `auth`. Belum ada sistem role — semua user = admin.

---

## Konvensi Proyek (untuk coding agent)

- **Stack**: Laravel 13, PHP 8.3+ (backend/API) + **React + Chart.js** (frontend interaktif) di atas Vite, Tailwind v4. SQLite (lokal) / MySQL (produksi). **Tanpa Livewire.**
- **Frontend**: UI kaya & chart pakai **React** (komponen) + **Chart.js** (grafik pembukuan/dashboard). Blade dipakai sebagai shell/entry yang me-mount komponen React. Alpine hanya untuk interaksi ringan yang belum dimigrasi.
- **Gaya kode**: ikuti PSR-12; jalankan `./vendor/bin/pint` sebelum commit.
- **Test**: gunakan Pest/PHPUnit — `php artisan test`. Setiap fitur wajib punya Feature test.
- **Logika bisnis**: taruh di `app/Actions` atau `app/Services`, bukan di controller.
- **Otorisasi**: selalu lewat Policy; jangan cek role secara manual di controller.
- **Migrasi**: satu perubahan skema = satu migrasi baru; jangan edit migrasi lama yang sudah rilis.
- **Commit**: pesan singkat & imperatif (mis. `add task move action`).

---

## Daftar Agent / Peran

### 1. Architect Agent
**Tanggung jawab**: struktur aplikasi, keputusan teknis, ERD, kontrak antar-layer.
- Menjaga [DESIGN.md](DESIGN.md) tetap akurat.
- Menyetujui perubahan skema DB & dependency baru.
- **Output**: diagram, ADR (Architecture Decision Record), review desain.

### 2. Backend Agent
**Tanggung jawab**: model, migrasi, action/service, policy, queue, event.
- Membangun API/route dan logika bisnis.
- Menulis Unit & Feature test untuk logika.
- **Skill kunci**: Eloquent, Policies, Jobs, Events (lihat SKILLS.md §A.1).

### 3. Frontend Agent
**Tanggung jawab**: komponen React, grafik Chart.js, styling Tailwind, Blade shell.
- Membangun UI: board kanban, modal task, form, dashboard, pembukuan (chart).
- Grafik (omzet, tren) memakai **Chart.js** via wrapper React.
- Memastikan responsif, aksesibel, konsisten dengan design system.
- **Skill kunci**: React, Chart.js, Tailwind v4, Vite, Blade shell (lihat SKILLS.md §A.2).

### 4. Database Agent
**Tanggung jawab**: skema, index, optimasi query, integritas data.
- Review migrasi & relasi.
- Mendeteksi N+1 dan menambah index/eager loading.

### 5. QA / Test Agent
**Tanggung jawab**: kualitas & regresi.
- Menulis/menjaga test suite, cek Definition of Done (SKILLS.md §D).
- Menjalankan `php artisan test` di CI sebelum merge.

### 6. Security Agent
**Tanggung jawab**: keamanan aplikasi.
- Audit otorisasi (Policy), validasi input, mass-assignment (`$fillable`).
- Cek CSRF, XSS, SQL injection, file upload aman.

### 7. DevOps Agent
**Tanggung jawab**: deployment & operasional.
- Konfigurasi env, queue worker (Supervisor), scheduler, migrasi produksi.
- Pipeline CI/CD, monitoring, backup.

---

## Alur Kerja Antar-Agent (per fitur)

```
Architect  →  desain & kontrak (DESIGN.md)
    │
    ├──►  Backend Agent   ─┐
    │                      ├─►  QA Agent  ─►  Security Agent  ─►  DevOps Agent
    ├──►  Frontend Agent  ─┘        │                                  │
    └──►  Database Agent  ─────────►│                              deploy
                                    │
                              (Definition of Done tercapai?)
```

1. **Architect** mendefinisikan kontrak & skema untuk fitur.
2. **Backend + Frontend + Database** membangun secara paralel.
3. **QA** memverifikasi DoD; **Security** audit; **DevOps** rilis.

---

## Penggunaan Coding Agent (Claude Code)

Saat meminta bantuan agent AI, sebutkan peran yang relevan, contoh:

- *"Sebagai Backend Agent, buat modul Pembukuan: model `Ledger` + migrasi + service rekap omzet."*
- *"Sebagai Frontend Agent, buat halaman Pembukuan (Blade + Alpine) dengan tabel transaksi & ringkasan per periode."*
- *"Sebagai QA Agent, tulis Feature test untuk pembuatan & pemindahan task di Kanban."*

Agent harus selalu:
1. Mengikuti **Konvensi Proyek** di atas.
2. Merujuk DESIGN.md untuk struktur & SKILLS.md untuk standar DoD.
3. Menyertakan/menjalankan test setelah perubahan non-trivial.
