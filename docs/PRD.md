# PRD — System AI Preneur

**Product Requirements Document**
Aplikasi manajemen bisnis untuk ekosistem AI Preneur: pipeline endorsement, papan Kanban produksi, OKR & KPI kuartalan, pembukuan keuangan, insight sosial media, template script, dan manajemen user berbasis peran.
Stack: Laravel 13 (PHP 8.5) + Inertia.js + **Vue 3** + Tailwind v4, MySQL/MariaDB. Referensi: [DESIGN.md](DESIGN.md) · [Schema.md](Schema.md) · [AGENTS.md](AGENTS.md)

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

Lima peran (`User::ROLES`). Akses menu sebagian dinamis (tabel `role_menu_access`, halaman `/akses`); sebagian dikunci keras di `User::canSee()`.

| Peran | Cakupan | Kelola (mutasi)? |
|-------|---------|------------------|
| **owner** | semua menu, termasuk User & Akses | ✅ penuh |
| **it** | semua menu (akses teknis penuh) | ✅ penuh |
| **manager** | operasional + OKR/KPI/Pembukuan/Tracking, **tanpa** User | ✅ board, task, target |
| **admin** | Pipeline, Kanban, Mindmap, Content, Insight, KPI | ✅ kartu (bukan struktur board) |
| **staff** | Kanban, Mindmap, KPI (rapor sendiri) | ❌ kartu saja di board kanban |

**Aturan kunci akses:**
- **OKR, Pembukuan, Tracking** = owner + manager saja (isinya omset & strategi), dikunci di kode — tak bisa dibuka lewat halaman Akses.
- **KPI board** = audiens lebih luas (owner/manager/it/admin); **rapor per orang** — owner & manager lihat semua, peran lain hanya barisnya sendiri (disaring di server).
- Staff yang **ditugasi** pada kartu boleh CRUD kartu di board kanban & berkomentar, tapi tak boleh menyentuh struktur (board/kolom) atau kartu Sales.

---

## 4. Ruang Lingkup — Modul

### 4.1 Dashboard (P0)
Ringkasan lintas modul: total entri & omzet (IDR/USD/gabungan), breakdown pipeline per board, distribusi kanban per progress, dan ringkasan pembukuan.

### 4.2 Pipeline (P0)
Tabel entri per board: account, endorse, output (multi), progress, tanggal posting/payment, status payment, jumlah IDR & USD, notes, ke_gilang, catatan. Filter (account, progress, payment, output, search), CRUD via modal, dan **report PDF** omzet per board / semua.

### 4.3 Kanban (P0)
Papan bergaya Trello:
- **Board & kolom dinamis** — owner/it/manager bisa tambah/ubah/hapus board (kategori) dan kolom (list); bukan enum tetap.
- **Drag-drop** kartu antar kolom (progress tersimpan otomatis).
- **Fitur kartu**: label warna (Urgent dsb.), deadline (highlight bila lewat), deskripsi, checklist/todo, lampiran file, komentar, dan arsip (kartu selesai).

### 4.4 OKR (P0) — owner + manager
Goal perusahaan per kuartal: **Objective** (kalimat tujuan) berisi **Key Result** terukur. Tiga sumber realisasi KR:
- **auto** — dihitung dari Insight (view, subscriber) & Pembukuan (omset). Tak bisa diketik tangan.
- **manual** — angka diperbarui sendiri (target tanpa sumber data).
- **kartu** — realisasi = kartu Kanban **todolist** yang ditautkan ke KR & sudah selesai. Inilah jembatan goal→eksekusi: goal ditulis di OKR, langkah pencapaiannya dibuat sebagai kartu todolist. Grafik tren 6 kuartal.

### 4.5 KPI (P0) — dua tab
- **Per Board**: target "berapa kartu selesai" tiap board + rekap ketepatan (tepat/terlambat/lewat deadline). Owner/manager/it/admin.
- **Per Orang**: rapor kerja tiap anggota (kartu, selesai, tepat/telat, rasio, rata-rata keterlambatan), basis `assigned_to`. Owner & manager lihat semua; peran lain hanya dirinya.

### 4.6 Pembukuan (P1)
Rekap keuangan (Vue + Chart.js): pemasukan/pengeluaran per bulan, laba, komposisi per kategori, snapshot inventaris, dan report PDF.

### 4.7 Insight (P1)
Snapshot performa akun & konten sosial media (`insight_accounts`, `insight_contents`) — jadi sumber realisasi OKR view/subscriber.

### 4.8 Script (P1)
Grid folder naskah. Data dikirim otomatis oleh agent lewat `POST /api/scripts` (bearer token).

### 4.9 Modul pendukung
Content (perencanaan konten mingguan), Order (pesanan customer), Absensi, Mindmap (mind-elixir), Tracking (ringkasan eksekutif read-only per board).

### 4.10 User & Akses (P0)
CRUD user + peran (owner/it). Halaman Akses mengelola `role_menu_access` per peran.

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

**Menambah task (Kanban):** user berwenang klik "+" kolom → isi judul/account/PJ/link → Simpan → kartu muncul.
**Kolaborasi:** buka kartu → tulis komentar / unggah lampiran / centang checklist. Staff yang ditugasi boleh komentar.
**Selesai:** user berwenang arsipkan kartu; muncul di tampilan "Arsip", bisa dikembalikan.
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
| **M3** | Migrasi SPA ke Inertia + **Vue 3** | ✅ |
| **M4** | Fitur kartu: deadline, arsip, label, deskripsi, lampiran, komentar | ✅ |
| **M5** | OKR/KR (auto/manual/kartu), KPI board + rapor per orang, tautan kartu→goal | ✅ |
| **M6** | Deploy (import SQL) + hardening | ⏳ |
