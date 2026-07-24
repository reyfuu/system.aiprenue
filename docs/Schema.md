# Schema — System AI Preneur

Struktur database. Produk: [PRD.md](PRD.md) · Arsitektur: [DESIGN.md](DESIGN.md) · Konvensi: [AGENTS.md](AGENTS.md).

- **DBMS**: MySQL / MariaDB (yang berjalan saat ini MariaDB 11.8). Dev boleh SQLite; deploy shared hosting via import `.sql`.
- **Sumber kebenaran**: file di `database/migrations/`. Satu perubahan = satu migrasi baru; migrasi lama tak pernah diedit.
- Semua tabel memakai `id` bigint auto-increment dan `created_at`/`updated_at` kecuali disebut lain.
- Kolom string yang secara logika enum (`progress`, `category`, `source`, dll.) **sengaja `varchar`, bukan `ENUM`** — daftar nilainya dinamis atau sering bertambah, dan `ENUM` MySQL menuntut migrasi tiap kali daftarnya berubah. Daftar sah dijaga di konstanta model + validasi controller, bukan di skema.

---

## Peta relasi

```
users ─┬─< pipelines            (assigned_to · created_by)
       ├─< objectives           (created_by)
       ├─< key_results          (owner_id · created_by)
       ├─< board_quarter_targets(created_by)
       ├─< pipeline_comments · pipeline_attachments · absences · mindmaps

categories(board) 1──n board_columns          (key = progress kartu)
objectives        1──n key_results  1──n pipelines   (key_result_id, nullable)
pipelines         n──n outputs      (pivot output_pipeline)
pipelines         1──n pipeline_comments · pipeline_attachments

transactions · inventories                (Pembukuan — berdiri sendiri)
insight_accounts · insight_contents       (Insight — berdiri sendiri)
scripts · contents · orders · role_menu_access · labels
```

Realisasi OKR tak disimpan sebagai kolom — **dihitung** dari `insight_*`, `transactions`, dan `pipelines`. Lihat §OKR.

---

## Auth & Akses

### `users`
| Kolom | Tipe | Catatan |
|---|---|---|
| name, email | varchar | email unik |
| password | varchar | bcrypt |
| role | varchar | `owner` · `manager` · `it` · `admin` · `staff` |

Peran bukan tabel; nilai string divalidasi ke `User::ROLES`. Logika hak akses ada di method `User::canSee($menu)`, `canManage()`, `canManageBoard($cat)`, `homeRoute()`.

### `role_menu_access`
Izin menu dinamis per peran (dikelola di halaman `/akses`).
| Kolom | Tipe | Catatan |
|---|---|---|
| role, menu | varchar | pasangan peran→menu |
| can_manage | bool | boleh mutasi, bukan sekadar lihat |

> Sebagian menu **tidak** dinamis: `okr`, `pembukuan`, `tracking` dikunci keras ke owner+manager di `User::canSee()` walau baris DB berkata lain — isinya angka keuangan/strategis. `kpi` sengaja dinamis (audiens lebih luas).

---

## Pipeline & Kanban

### `categories` — board dinamis
| Kolom | Tipe | Catatan |
|---|---|---|
| key | varchar | **unik**; dipakai sbg `pipelines.category` |
| name | varchar | label tampil |
| type | varchar | `pipeline` (Sales, hanya board `sales`) · `kanban` |
| section | varchar? | pengelompokan sidebar |
| super_admin_only | bool | |
| created_by | FK users? | nullOnDelete |

### `board_columns` — kolom kanban
| Kolom | Tipe | Catatan |
|---|---|---|
| board_key | varchar | = `categories.key` |
| key | varchar | dipakai sbg `pipelines.progress` |
| name, color | varchar | `color` = kelas Tailwind, wajib di safelist `app.css` |
| position | int | urutan; **kolom terakhir = tahap "selesai"** |

### `pipelines` — kartu/entri (Sales + Kanban)
Tabel terbesar; dipakai bersama modul Sales dan Kanban (dibedakan `categories.type`).
| Kolom | Tipe | Catatan |
|---|---|---|
| category | varchar | = board key |
| progress | varchar | = key kolom board (string dinamis, bukan enum) |
| position | int | urutan dalam kolom |
| jenis | varchar? | endorse/coaching_1on1/coaching_perusahaan/agensi/speaker (atribut Sales; null utk kartu kanban) |
| account | enum(fk, ai_preneur) | |
| assigned_to | FK users? | PJ kartu — **dasar rapor KPI per orang** |
| created_by | FK users? | pembuat; nullOnDelete |
| **key_result_id** | FK key_results? | tautan ke goal OKR (todolist saja); nullOnDelete |
| endorse | varchar | judul kartu |
| description, notes | text? | |
| deadline | date? | **penentu kuartal kartu** & ketepatan waktu |
| **completed_at** | timestamp? | **satu-satunya penanda "selesai"** (lihat aturan) |
| done | bool | flag Trello; UI saja, KPI tak memakainya |
| payment_status | enum(belum, dp, lunas) | Sales |
| amount_idr / amount_usd, dp1..dp3 | decimal? | nilai deal Sales |
| labels | json | array; **maks 1 item** (pilih-satu), ditegakkan server |
| todos | json | checklist |
| kontak_wa/gmail/ig, link | varchar? | |
| archived_at, deleted_at | timestamp? | arsip + soft delete |

**Aturan `completed_at` (dasar seluruh analitik ketepatan):**
- Diisi saat kartu masuk **kolom paling kanan** (drag) atau ditandai selesai; dikosongkan saat dibatalkan.
- Hanya kartu yang **benar-benar berpindah** ke kolom terakhir yang distempel — kartu yang cuma ikut dalam kiriman reorder tak disentuh (cegah "semua terbaca terlambat").
- Stempel pertama dipertahankan saat diselesaikan ulang (cegah kartu telat "dirapikan" jadi tepat waktu).

**Ketepatan** (`Pipeline::ketepatan()`): `tepat` (completed_at ≤ deadline) · `terlambat` (> deadline) · `lewat` (belum selesai & deadline lewat) · `null` (tanpa deadline / masih berjalan). Perbandingan per **tanggal**, bukan detik.

### Pendukung kartu
- **`outputs`** + **`output_pipeline`** (pivot) — tag output multi (`name`, `color`).
- **`pipeline_comments`** — `pipeline_id`, `user_id`, `body`.
- **`pipeline_attachments`** — `path`, `name`, `mime`, `size` (disk `public`, maks 10 MB).
- **`labels`** — definisi label (`name`, `color`), dikelola owner. Kartu menyimpan **snapshot** `{name,color}`-nya sendiri; mengubah definisi tak menyentuh kartu lama.

---

## OKR & KPI (kuartalan)

Kuartal diturunkan, bukan disimpan: helper `App\Support\Quarter` memetakan tanggal↔kuartal.

### `objectives` — goal per kuartal
| Kolom | Tipe | Catatan |
|---|---|---|
| year | smallint | index `(year, quarter)` |
| quarter | tinyint | 1–4 |
| title | varchar | kalimat tujuan (tak diukur langsung) |
| description | text? | |
| position | int | urutan |
| created_by | FK users? | |

Progress Objective = **rata-rata persen Key Result-nya, tiap KR dibatasi 100% dulu** (`Objective::progress()`) — cegah satu KR 300% menutupi dua KR 0%.

### `key_results` — bagian OKR yang terukur
| Kolom | Tipe | Catatan |
|---|---|---|
| objective_id | FK objectives | **cascadeOnDelete** (KR tanpa Objective tak berarti) |
| title | varchar | |
| source | varchar | `auto` · `manual` · `kartu` |
| metric | varchar? | wajib saat `auto`: `view`/`subscriber`/`omset` |
| target | decimal(20,2) | |
| actual_manual | decimal(20,2)? | hanya saat `manual` |
| unit | varchar | `angka`/`rupiah`/`persen` |
| owner_id | FK users? | PJ (nullOnDelete) |
| created_by | FK users? | |

**Realisasi KR (`KeyResult::actual()`) — tak ada angka realisasi yang diketik untuk auto/kartu:**
- `auto` → dihitung dari Insight/Pembukuan lewat `OkrMetrics::realisasi()` (satu kali per kuartal, dioper ke tiap KR — hindari N+1).
- `kartu` → jumlah `pipelines` yang `key_result_id` = KR ini **dan** `completed_at` terisi. Ini jembatan goal → papan kerja: kartu todolist ditautkan ke KR, menyelesaikannya menggerakkan angka.
- `manual` → `actual_manual`. Endpoint `updateActual` **menolak** (422) source `auto`/`kartu` — angka otomatis yang bisa ditimpa tangan berhenti bisa dipercaya.

### `board_quarter_targets` — target KPI board
| Kolom | Tipe | Catatan |
|---|---|---|
| board_key | varchar | = `categories.key` |
| year, quarter | smallint/tinyint | |
| target_done | int | berapa kartu harus selesai |
| note | text? | |
| created_by | FK users? | |

KPI board & rapor per orang tak punya tabel realisasi — dihitung dari `pipelines` (deadline dalam kuartal, `completed_at`) di `KpiController`.

---

## Insight (sumber realisasi OKR view/subscriber)

### `insight_accounts` — snapshot akun per tanggal
`platform`, `akun`, `nama_akun`, `tanggal`, `followers`, `media_count`, `reach`, `impressions`, `profile_views`, `link_clicks`.
> Realisasi **subscriber** = snapshot **terakhir** per akun dalam kuartal, dijumlah antar-akun — **bukan** jumlah seluruh baris (kalau dijumlah, angkanya melonjak salah).

### `insight_contents` — metrik per konten
`platform`, `content_id`, `judul`, `content_type`, `published_at`, `views`, `reach`, `impressions`, `likes`, `comments`, `shares`, `saves`, `watch_time_seconds`, `followers_gained`, dll.
> Realisasi **view** = jumlah `views` konten yang `published_at` jatuh dalam kuartal.

---

## Pembukuan

- **`transactions`** — `type` enum(pemasukan, pengeluaran), `category`, `description`, `amount_idr`, `date`. Realisasi **omset** OKR = jumlah `pemasukan` dalam kuartal.
- **`inventories`** — `name`, `qty`, `unit_value_idr`, `month`.

---

## Konten, Script, Order, Absensi, Mindmap

- **`scripts`** — `brand`, `title`, `body`, `generated_for`, `source_pdf_path`. Diisi agent lewat `POST /api/scripts` (bearer token).
- **`contents`** — perencanaan konten mingguan: `comp`, `jenis_postingan`, `kategori`, `inti_pesan`, `hook_material`, `brief_original`, `script_remake`, `editor`, `progress`, `tanggal_upload`, link-link hasil.
- **`orders`** + **`order_output`** (pivot) — pesanan: `tipe_order`, `account`, `tanggal_deadline`, `nama_customer`, kontak, `tipe_pembayaran`, `total_idr/usd`, `bukti_bayar`, `invoice`.
- **`absences`** — `user_id`, `type`, `start_date`, `end_date`, `reason`, `attachment_path`, `status`.
- **`mindmaps`** — `user_id`, `title`, `data` (json mind-elixir).

---

## Konvensi FK

| Pola | Dipakai untuk | Alasan |
|---|---|---|
| `nullOnDelete` | `created_by`, `owner_id`, `assigned_to`, `key_result_id` | Menghapus user/KR tak boleh menghapus pekerjaan; yang hilang cukup kaitannya. |
| `cascadeOnDelete` | `key_results.objective_id`, pivot | Baris anak tak punya arti tanpa induk. |
| soft delete | `pipelines` | Kartu bisa dipulihkan. |
