# Strategi Recovery — Checkpoint + Point-in-Time (praktis)

Latar: pernah kehilangan data (Objective #9/#10, Pipeline #15–19) karena backup
hosting stale/mislabel dan audit log tak menyimpan nilai lama saat delete.

## Kenapa bukan PITR binlog "murni"

PITR penuh (replay MySQL binlog ke detik mana pun) **tidak tersedia** di shared
hosting Hostinger — akses binlog & `mysqlbinlog` tidak diberikan ke akun user.
Jadi strategi ini mendekati PITR dengan **checkpoint sering + audit log**:

- **Recovery point** = checkpoint terakhir (granularitas = interval cron).
- **Antar checkpoint** = pulihkan/kembalikan per-baris dari tabel `audit_logs`
  (`old_values` untuk create/update/**delete**, `new_values` untuk create/update).
  Delete kini menyimpan snapshot penuh baris → bisa di-insert ulang.

## Komponen

| Bagian | Perkakas |
|---|---|
| Checkpoint | `php artisan db:checkpoint --keep=N` → `storage/app/backups/checkpoint-YYYYMMDD-HHMMSS.sql.gz` (rotasi simpan N terbaru) |
| Restore | `php artisan db:restore [file] [--force]` (destruktif; wajib ketik nama DB) |
| Recovery baris | Halaman **Audit Log** (owner/it) → kolom Detail → Sebelum/Sesudah |

Password DB diambil dari config (`.env`), dikirim ke `mysqldump`/`mysql` lewat
env `MYSQL_PWD` — tak muncul di daftar proses (`ps`). Dump gzip di
`storage/app/backups/` (di luar webroot, ter-gitignore).

## Setup cron di hPanel (server)

hPanel → **Advanced → Cron Jobs** → Add. Contoh tiap jam, simpan 48 (≈2 hari):

```
0 * * * * /opt/alt/php84/usr/bin/php /home/u864765086/domains/aipreneur.co.id/public_html/app/artisan db:checkpoint --keep=48
```

Saran retensi: `--keep=48` (per jam ≈ 2 hari) atau jalankan 2× — per jam
(`--keep=48`) + harian tengah malam (interval terpisah, folder sama, `--keep`
lebih besar). Sesuaikan dengan kuota disk.

## Offsite (disarankan)

Checkpoint tetap di server yang sama = tak tahan bila akun hosting hilang.
Berkala unduh `storage/app/backups/` ke laptop (mis. `scp`/rsync) atau salin ke
Drive.

## Prosedur pemulihan

1. **Rollback total ke titik aman:** `php artisan db:restore checkpoint-XX.sql.gz`
   (restore checkpoint terdekat SEBELUM insiden).
2. **Pemulihan selektif (1 record terhapus):** buka Audit Log, cari baris
   `delete`, salin `old_values`, `INSERT` ulang manual (perhatikan FK).

## Deploy

File command ikut kode → **upload** ke server (deploy = upload, bukan git pull).
Folder `storage/app/backups/` dibuat otomatis saat command pertama jalan.
