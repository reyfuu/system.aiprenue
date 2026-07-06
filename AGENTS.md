# AGENTS — Peran & Agent Pembangun Sistem

Dokumen ini mendefinisikan **agent/peran** (baik manusia maupun AI-assisted) yang bertanggung jawab membangun dan memelihara Sistem Manajemen Proyek/Task.

Referensi: [DESIGN.md](DESIGN.md) · [SKILLS.md](SKILLS.md)

> Format ini juga kompatibel sebagai `AGENTS.md` — dibaca oleh coding agent (Claude Code dsb.) untuk memahami konvensi proyek.

---

## Konvensi Proyek (untuk coding agent)

- **Stack**: Laravel 11, PHP 8.2+, Blade + Tailwind + Livewire, MySQL/PostgreSQL, Redis.
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
**Tanggung jawab**: Blade view, komponen Tailwind, Livewire/Alpine.
- Membangun UI: board kanban, modal task, form, dashboard.
- Memastikan responsif, aksesibel, konsisten dengan design system.
- **Skill kunci**: Blade, Tailwind, Livewire (lihat SKILLS.md §A.2).

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

- *"Sebagai Backend Agent, buat `Task` model + migrasi + `TaskPolicy` sesuai DESIGN.md §3."*
- *"Sebagai Frontend Agent, buat komponen Livewire `KanbanBoard` dengan drag-drop antar kolom status."*
- *"Sebagai QA Agent, tulis Feature test untuk pembuatan & pemindahan task."*

Agent harus selalu:
1. Mengikuti **Konvensi Proyek** di atas.
2. Merujuk DESIGN.md untuk struktur & SKILLS.md untuk standar DoD.
3. Menyertakan/menjalankan test setelah perubahan non-trivial.
