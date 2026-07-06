# PRD — Pipeline FK-AI Preneur

**Product Requirements Document**
Sistem manajemen pipeline endorsement/kolaborasi produk AI.
Stack: Laravel 11 + Blade + Tailwind + Livewire. Referensi: [DESIGN.md](DESIGN.md) · [SKILLS.md](SKILLS.md) · [AGENTS.md](AGENTS.md)

---

## 1. Latar Belakang & Masalah

Saat ini pipeline endorsement dikelola di Google Sheets ("NEW RAVE-RAVELOUX / PIPELINE FK-AI PRENEUR"). Sheet menampung banyak kolaborasi endorse produk AI (mis. Halo AI, Seedance, Dreamina) dengan status produksi, pembayaran (IDR & USD), dan handoff ke tim.

**Keterbatasan spreadsheet:**
- Sulit filter/laporan (mis. total outstanding payment, progress per akun).
- Tidak ada validasi, riwayat perubahan, atau notifikasi.
- Rentan salah edit; status ("Ke Gilang", "Payment") hanya warna manual.
- Tidak ada dashboard ringkasan revenue & progress.

**Tujuan produk:** menggantikan sheet dengan aplikasi web yang terstruktur, dapat difilter, punya status jelas, dan menyajikan ringkasan keuangan & progress.

---

## 2. Tujuan & Metrik Sukses

| Tujuan | Metrik |
|--------|--------|
| Sentralisasi data endorsement | 100% baris sheet termigrasi |
| Visibilitas pembayaran | Dashboard total IDR + USD, outstanding, lunas |
| Kejelasan progress | Filter per status Progress & Ke Gilang |
| Efisiensi input | Tambah/edit entri < 30 detik |
| Auditability | Semua perubahan tercatat (activity log) |

---

## 3. Pengguna & Peran

| Peran | Kebutuhan |
|-------|-----------|
| **Owner/Admin (FK)** | Lihat semua, kelola user, akses laporan keuangan penuh |
| **Manager** | Input & update entri, pantau progress & payment |
| **Tim Produksi** | Update status Progress/Output entri yang ditangani |

---

## 4. Ruang Lingkup — Struktur Data (Tabel Pipeline)

Satu baris = satu entri endorsement. Kolom **hanya sampai "NOTES"** sesuai sheet (kolom A–L).

| # | Field | Header Sheet | Tipe | Nilai / Aturan |
|---|-------|--------------|------|----------------|
| 1 | `account` | ACCOUNT | enum | `FK`, `AI Preneur` — wajib |
| 2 | `coaching` | COACHING | string | opsional |
| 3 | `speaker` | SPEAKER | string | opsional |
| 4 | `endorse` | ENDORSE | string | nama produk — wajib |
| 5 | `outputs` | OUTPUT | multi-tag | `Youtube`, `Reels/TikTok`, `Agency`, `Foto`, `Video` (bisa lebih dari satu) |
| 6 | `progress` | PROGRESS | enum | `Editing`, `Progress`, `Done` |
| 7 | `tanggal_posting` | TANGGAL POSTING | date | opsional |
| 8 | `tanggal_payment` | TANGGAL PAYMENT | date | opsional |
| 9 | `payment_status` | SUDAH/BELUM PAYMENT | enum | `Belum`, `DP`, `Lunas` |
| 10 | `amount_idr` | JUMLAH PAYMENT IDR | decimal | nullable, rupiah |
| 11 | `amount_usd` | JUMLAH PAYMENT USD | decimal | nullable, dolar |
| 12 | `notes` | NOTES | text | opsional |

> Kolom "Ke Gilang" & "Catatan" (M–N) **di luar lingkup** — lihat Non-Goals §8.
> Catatan: `amount_idr` dan `amount_usd` bersifat eksklusif per entri (satu entri umumnya salah satu), tapi keduanya boleh terisi.

---

## 5. Fitur & Kebutuhan Fungsional

### 5.1 Manajemen Entri (P0)
- **Tabel utama** menampilkan semua entri (kolom seperti sheet) dengan sticky header.
- **Tambah/Edit/Hapus** entri lewat modal (Livewire).
- **Inline edit** untuk status cepat (Progress, Payment, Ke Gilang) — mirip dropdown berwarna di sheet.
- **Soft delete** + pemulihan.

### 5.2 Status Berwarna (P0)
Badge warna mengikuti konvensi sheet:
- Progress: `Done`=hijau, `Progress`=ungu, `Editing`=hijau muda.
- Payment: `Lunas`=hijau, `DP`=kuning, `Belum`=merah.

### 5.3 Filter & Pencarian (P0)
- Filter berdasarkan: Account, Progress, Payment status, Output.
- Search teks pada Endorse/Notes.
- Sort per kolom (tanggal, jumlah).

### 5.4 Dashboard Ringkasan (P1)
- Total revenue IDR & USD (semua / lunas / outstanding).
- Jumlah entri per status Progress.
- Outstanding payment (Belum + DP).
- Breakdown per Account (FK vs AI Preneur).

### 5.5 Notifikasi & Aktivitas (P2)
- Tandai entri "Belum" payment melewati tanggal → highlight.
- Log aktivitas per entri (siapa mengubah apa).

### 5.6 Import/Export (P1)
- Import awal dari CSV Google Sheets.
- Export ke CSV/Excel dengan filter aktif.

---

## 6. Kebutuhan Non-Fungsional

- **Autentikasi & RBAC** — sesuai peran §3 (via Laravel Policies).
- **Responsif** — usable di desktop & tablet.
- **Performa** — tabel 500+ baris tanpa lag (pagination/lazy load, hindari N+1).
- **Audit** — activity log untuk perubahan payment & status.
- **Lokalisasi** — label Bahasa Indonesia, format Rupiah & USD.

---

## 7. Alur Utama (User Flow)

**Menambah endorsement baru:**
1. Klik "Tambah Entri" → modal.
2. Pilih Account, isi Endorse, pilih Output (multi), set Progress.
3. Isi tanggal & jumlah payment bila ada.
4. Simpan → muncul di tabel dengan badge warna.

**Update pembayaran:**
1. Buka entri / inline edit kolom Payment.
2. Ubah `Belum` → `DP` → `Lunas`, isi tanggal payment.
3. Dashboard revenue ter-update otomatis.

---

## 8. Di Luar Lingkup (Non-Goals)

- Kolom "Ke Gilang" & "Catatan" (M–N) dan kolom setelahnya — tidak dipakai untuk sekarang.
- Integrasi langsung ke payment gateway.
- Manajemen konten/aset media (hanya tautan/notes).
- Fitur coaching & speaker sebagai modul terpisah — untuk sekarang hanya kolom teks.

---

## 9. Milestone Rilis

| Milestone | Isi |
|-----------|-----|
| **M1** | Auth + RBAC, tabel pipeline CRUD, status berwarna, import CSV |
| **M2** | Filter, search, sort, inline edit |
| **M3** | Dashboard revenue & progress, export |
| **M4** | Activity log, notifikasi deadline payment, polish |

---

## 10. Pertanyaan Terbuka

- Apakah "COACHING" & "SPEAKER" akan jadi modul/kolom tersendiri nanti? (kini kolom teks kosong)
- Kurs USD→IDR untuk total gabungan di dashboard: manual atau otomatis?
- Siapa saja yang boleh melihat nominal payment (semua peran atau Admin/Manager saja)?
