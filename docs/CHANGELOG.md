# Changelog

Semua perubahan penting pada proyek ini dicatat di sini.
Format mengikuti gaya [Keep a Changelog](https://keepachangelog.com/id/1.1.0/).

## [Belum dirilis] — branch `feature/okr-kpi-kinerja`

Menambahkan tiga modul strategi & kinerja tim (OKR, KPI, rapor per orang),
menjembatani goal → eksekusi lewat kartu Kanban, mengekspos strategi OKR
ke AI lewat MCP server, plus sejumlah perbaikan bug yang ditemukan saat
mencoba data asli.

### Ditambahkan

- **OKR perusahaan (`/okr`)** — owner + manager.
  - Struktur **Objective → Key Result**, bukan tiga metrik datar.
  - Tiga sumber realisasi KR:
    - `auto` — dihitung dari Insight (view, subscriber) & Pembukuan (omset); tak bisa diketik tangan.
    - `manual` — angka diisi sendiri.
    - `kartu` — realisasi = kartu Kanban todolist tertaut yang selesai.
  - Progress Objective = rata-rata persen KR; tiap KR dibatasi 100% dulu
    (satu KR 300% tak menutupi dua KR 0%).
  - Grafik tren 6 kuartal + salin KR dari kuartal lalu.
- **Jembatan goal → eksekusi.** Goal ditulis di OKR; langkah pencapaiannya =
  kartu Kanban todolist yang ditautkan ke KR. Penautan dikelola dari halaman OKR
  (panel "Kelola langkah"): buat kartu baru, tautkan yang ada, lepas tautan.
  Kanban murni untuk delegasi — tak ada UI OKR di sana.
- **KPI (`/kpi`)** — dua tab:
  - *Per Board*: target "berapa kartu selesai" + rekap ketepatan (tepat/terlambat/lewat deadline).
  - *Per Orang*: rapor kerja tiap anggota (basis `assigned_to`) — kartu, selesai,
    tepat/telat, rasio, rata-rata keterlambatan. Owner/manager lihat semua;
    peran lain hanya dirinya (disaring di server).
- **Penilaian ketepatan waktu.** "Selesai" = `completed_at` terisi (saat kartu
  masuk kolom terakhir), bukan flag `done`, bukan nama kolom. Empat status:
  tepat / terlambat / lewat (belum selesai, deadline lewat) / tak dinilai.
  Rasio hanya dari kartu yang sudah selesai.
- **MCP server — strategi OKR lewat AI.** Empat tool: `list_okr`,
  `create_objective`, `create_key_result`, `link_task_to_kr`. Realisasi memakai
  rumus sama persis dengan aplikasi — angka MCP = angka `/okr`, terverifikasi live.
- **Support & data.** `app/Support/OkrMetrics.php`, `KinerjaOrang.php`, `Quarter.php`;
  seeder demo `DemoOkrKpiSeeder.php`; tabel `objectives`, `key_results`,
  `board_quarter_targets`; kolom `created_by` & `completed_at`; tautan kartu→KR.
- **Tes.** `OkrTest`, `KpiOrangTest`, `KanbanStaffAccessTest`, `SalesPipelineTest`.

### Diperbaiki

- **Stempel selesai massal.** Satu drag menandai semua kartu lama "hari ini" →
  seluruh papan terbaca terlambat. Diperbaiki + migrasi pembersih data
  (`bersihkan_stempel_selesai_massal`).
- **Label kartu.** Dicocokkan lewat warna (dua label sewarna saling hapus) →
  diperbaiki ke nama + jadi pilih-satu (radio), ditegakkan server.
- **Badge Order di layar 1366.** Badge oval dua baris & tabel meluber 107px
  (kolom Aksi terpotong) → 0px, diukur lewat browser asli.

### Dokumentasi

- Semua `.md` (kecuali README) dikumpulkan ke `docs/`; ignore `/docs` dihapus.
- `docs/Schema.md` baru (skema semua tabel).
- PRD/DESIGN/AGENTS disegarkan: stack React→Vue (usang), peran diperbaiki, modul baru.

### Verifikasi

- `npm run build` sukses.
- 286/287 tes lolos. Satu yang merah (`ScriptTest`) sudah gagal sebelum branch ini —
  dipastikan lewat `git stash`, tak berkaitan.
