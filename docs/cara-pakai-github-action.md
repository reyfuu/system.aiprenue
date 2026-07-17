# Cara Pakai GitHub Actions — Daily Script Rave

Panduan pemula untuk menjalankan agen pembuat 30 naskah per brand dan
mengirim hasilnya ke menu Script System AI Preneur.

## Gambaran alur

```text
GitHub Actions
  → Anthropic membuat naskah
  → agen mengirim naskah ke API aplikasi
  → aplikasi menyimpan naskah
  → pengguna mengunduh paket PDF dari menu Script
```

Konfigurasi dilakukan di dua tempat:

1. Server aplikasi Laravel menyimpan `SCRIPT_AGENT_TOKEN`.
2. GitHub repo `Daily-Script-Rave` menyimpan `ANTHROPIC_API_KEY` dan
   `APP_SCRIPT_TOKEN` sebagai repository secrets.

> Jangan pernah menaruh token atau API key asli di kode, commit, screenshot,
> issue, atau chat. Jika pernah terlihat orang lain, cabut dan buat yang baru.

## 1. Buat token aplikasi

Jalankan di terminal server:

```bash
php -r 'echo bin2hex(random_bytes(24)), PHP_EOL;'
```

Salin hasilnya ke tempat aman. Nilai ini akan dipasang di server dan GitHub.

## 2. Pasang token di server aplikasi

Masuk ke folder aplikasi, lalu buka `.env`:

```bash
nano .env
```

Pastikan konfigurasi berikut tersedia:

```env
APP_ENV=production
APP_DEBUG=false
SCRIPT_AGENT_TOKEN=TOKEN_BARU
```

Jangan beri spasi setelah tanda `=`. Simpan Nano dengan `Control + O`, tekan
`Enter`, lalu keluar dengan `Control + X`.

Muat ulang konfigurasi Laravel:

```bash
php artisan optimize:clear
```

Periksa bahwa token terbaca tanpa menampilkan nilainya:

```bash
php artisan tinker --execute="echo strlen((string) config('services.script_agent.token'));"
```

Hasil harus lebih dari `0`; token dari perintah di atas panjangnya `48` karakter.

## 3. Buat API key Anthropic

Buka [Anthropic Console](https://console.anthropic.com/settings/keys), lalu:

1. Cabut API key lama jika pernah bocor.
2. Klik **Create Key**.
3. Beri nama, misalnya `daily-script-production`.
4. Salin key baru ke tempat aman.

API key Anthropic tidak perlu dipasang di `.env` Laravel karena model dijalankan
oleh GitHub Actions, bukan server aplikasi.

## 4. Tambahkan repository secrets di GitHub

Buka repo privat `raventcreative/Daily-Script-Rave`, kemudian:

1. Klik **Settings**.
2. Pilih **Secrets and variables** → **Actions**.
3. Pilih tab **Secrets**.
4. Klik **New repository secret**.

Tambahkan dua secret berikut:

| Name | Value |
|---|---|
| `ANTHROPIC_API_KEY` | API key baru dari Anthropic Console |
| `APP_SCRIPT_TOKEN` | Nilai yang sama dengan `SCRIPT_AGENT_TOKEN` di server |

`APP_SCRIPT_URL` tidak perlu dibuat selama endpoint production tetap:

```text
https://app.aipreneur.co.id/api/scripts
```

Agen sudah memakai alamat tersebut sebagai default. Tambahkan
`APP_SCRIPT_URL` hanya jika memakai domain lain atau staging.

GitHub menyembunyikan nilai secret setelah disimpan. Itu perilaku normal.

Panduan resmi: [Using secrets in GitHub Actions](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets?tool=webui).

## 5. Jalankan workflow secara manual

Di repo `Daily-Script-Rave`:

1. Klik tab **Actions**.
2. Pilih workflow **Script** di sidebar kiri.
3. Klik **Run workflow**.
4. Pilih branch `main`.
5. Klik tombol hijau **Run workflow**.

Workflow menjalankan tiga job:

```text
raveloux
ravetailor
fk
```

Panduan resmi: [Manually running a workflow](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow?tool=webui).

## 6. Periksa hasil workflow

Klik workflow yang baru dijalankan. Hasil berhasil ditandai tiga job hijau:

```text
✓ raveloux
✓ ravetailor
✓ fk
```

Log pengiriman yang berhasil berisi respons seperti:

```text
ok: true
jumlah: 30
```

Setelah selesai, buka:

```text
https://app.aipreneur.co.id/script
```

Setiap brand seharusnya memiliki satu paket PDF terbaru dengan 30 naskah.

## 7. Jadwal otomatis

Workflow juga berjalan otomatis setiap Jumat, Sabtu, dan Minggu pukul 00.00
WIB. Menjalankan manual hanya diperlukan untuk tes, rerun kegagalan, atau
permintaan paket di luar jadwal.

## Troubleshooting

| Gejala | Penyebab yang mungkin | Tindakan |
|---|---|---|
| `ANTHROPIC_API_KEY` kosong | Secret belum dibuat atau namanya salah | Buat secret dengan nama persis `ANTHROPIC_API_KEY` |
| `401 Token tidak sah` | Token GitHub dan server berbeda | Samakan `APP_SCRIPT_TOKEN` dengan `SCRIPT_AGENT_TOKEN`, lalu `php artisan optimize:clear` |
| `422` | Format brand atau naskah ditolak aplikasi | Buka detail respons di log job |
| Connection error | Domain aplikasi tidak dapat dijangkau | Buka endpoint aplikasi dan periksa DNS/SSL |
| Credit/billing error | Saldo atau billing Anthropic bermasalah | Periksa billing Anthropic Console |
| Tombol **Run workflow** tidak ada | Workflow belum berada di branch default atau akun tidak punya akses tulis | Pastikan memilih repo dan branch `main` yang benar |

Jika meminta bantuan, kirim screenshot bagian error saja. Tutup atau sensor
semua API key, bearer token, password, nama database, dan kredensial server.

## Video

- [Playlist GitHub Actions di YouTube](https://www.youtube.com/playlist?list=PLOQjd5dsGSxKC4K12-iLnla5E7rjJr_Ts)
- [Pencarian YouTube: GitHub Actions Secrets step by step](https://www.youtube.com/results?search_query=github+actions+secrets+step+by+step)
