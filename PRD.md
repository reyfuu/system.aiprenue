# PRD — System AI Preneur

**Product Requirements Document**
Aplikasi manajemen bisnis untuk ekosistem AI Preneur: pipeline endorsement, papan Kanban produksi, pembukuan keuangan, template script, dan manajemen user berbasis peran.
Stack: Laravel 13 + Inertia.js + React 19 + Tailwind v4 (SQLite). Referensi: [DESIGN.md](DESIGN.md) · [SKILLS.md](SKILLS.md) · [AGENTS.md](AGENTS.md)

---

## 1. Latar Belakang & Masalah

Operasional AI Preneur (endorsement produk AI, produksi konten, keuangan) sebelumnya dikelola manual di Google Sheets. Keterbatasan: sulit filter/laporan, tak ada status/role jelas, rentan salah edit, tak ada dashboard ringkasan, dan kolaborasi tim (assign, komentar, lampiran) tidak terstruktur.

**Tujuan produk:** satu aplikasi terpusat untuk mengelola pipeline, alur produksi (Kanban), keuangan, dan tim — dengan hak akses per peran dan ringkasan yang jelas.

---

## 2. Tujuan & Metrik Sukses

| Tujuan | Metrik |
|--------|--------|
| Sentralisasi data endorsement | 100% baris sheet termigrasi (via import SQL) |
| Visibilitas pembayaran | Dashboard total IDR + USD, outstanding, lunas |
| Alur produksi jelas | Kanban dinamis dengan progress, deadline, arsip |
| Kolaborasi tim | Komentar & lampiran per kartu, penugasan staff |
| Kontrol akses | Hak per peran (menu + aksi) tegas di server |

---

## 3. Pengguna & Peran

| Peran | Menu yang dilihat | Bisa kelola? |
|-------|-------------------|--------------|
| **super_admin** | semua | ✅ penuh (CRUD kartu/board/kolom/lampiran) |
| **it** | semua | ✅ setara super_admin |
| **admin** | Script, Kanban | ❌ lihat saja |
| **editor** | Kanban | ❌ lihat + komentar |
| **staff** | Kanban | ❌ lihat + komentar |

Staff yang **ditugasi** pada sebuah kartu tetap bisa berkomentar meski tidak boleh mengedit.

---

## 4. Ruang Lingkup — Modul

### 4.1 Dashboard (P0)
Ringkasan lintas modul: total entri & omzet (IDR/USD/gabungan), breakdown pipeline per board, distribusi kanban per progress, dan ringkasan pembukuan.

### 4.2 Pipeline (P0)
Tabel entri per board: account, endorse, output (multi), progress, tanggal posting/payment, status payment, jumlah IDR & USD, notes, ke_gilang, catatan. Filter (account, progress, payment, output, search), CRUD via modal, dan **report PDF** omzet per board / semua.

### 4.3 Kanban (P0)
Papan bergaya Trello:
- **Board & kolom dinamis** — super_admin bisa tambah/ubah/hapus board (kategori) dan kolom (list); bukan enum tetap.
- **Drag-drop** kartu antar kolom (progress tersimpan otomatis).
- **Fitur kartu**: label warna (Urgent dsb.), deadline (highlight bila lewat), deskripsi, checklist/todo, lampiran file, komentar, dan arsip (kartu selesai).

### 4.4 Pembukuan (P1)
Rekap keuangan (React + Chart.js): pemasukan/pengeluaran per bulan, laba, komposisi per kategori, snapshot inventaris, dan report PDF.

### 4.5 Script (P1)
Grid folder naskah (template UI). Data folder dikirim otomatis oleh Hermes agent ke `public/scripts/*`.

### 4.6 User Management (P0)
CRUD user + peran. Hanya super_admin/it yang mengakses.

---

## 5. Kebutuhan Non-Fungsional

- **Autentikasi & otorisasi** — session auth + otorisasi dua lapis (menu + aksi) via middleware `EnsureMenuAccess` dan `User::canManage()`.
- **Responsif** — usable di desktop & tablet.
- **Lokalisasi** — Bahasa Indonesia, format Rupiah & USD.
- **Kurs otomatis** — USD→IDR dari open.er-api.com (cache 12 jam, fallback Rp 16.000).
- **Soft delete** pada pipeline untuk pemulihan.
- **Batas lampiran** 10 MB/file (disk `public`).

---

## 6. Alur Utama

**Menambah task (Kanban):** super_admin klik "+" kolom → isi judul/account/PJ/link → Simpan → kartu muncul.
**Kolaborasi:** buka kartu → tulis komentar / unggah lampiran / centang checklist. Staff yang ditugasi boleh komentar.
**Selesai:** super_admin arsipkan kartu; muncul di tampilan "Arsip", bisa dikembalikan.
**Pembayaran:** edit kartu/entri → ubah status payment → dashboard omzet ter-update.

---

## 7. Di Luar Lingkup (Non-Goals)

- Integrasi payment gateway.
- Notifikasi email / realtime (belum).
- Reorder kolom kanban via drag antar-list (urutan = urutan pembuatan).
- Manajemen aset media (hanya tautan/lampiran).

---

## 8. Milestone (status)

| Milestone | Isi | Status |
|-----------|-----|:------:|
| **M1** | Auth + peran, Pipeline CRUD, report PDF, dashboard | ✅ |
| **M2** | Kanban dinamis, pembukuan (Chart.js), user management, script | ✅ |
| **M3** | Migrasi SPA ke Inertia + React | ✅ |
| **M4** | Fitur kartu: deadline, arsip, label, deskripsi, lampiran, komentar | ✅ |
| **M5** | Deploy (import SQL) + hardening | ⏳ |
