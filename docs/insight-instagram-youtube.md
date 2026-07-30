# Panduan Integrasi Analytics — Instagram & YouTube

Panduan pasang menu **Insight** yang menarik angka dari Instagram dan YouTube.
Ditulis untuk kondisi nyata repo ini: produksi di **shared hosting tanpa Node**,
dan ada **VPS** yang sudah menjalankan cron (tempat 9router & agen Script).

Baca urut. Bagian yang bikin proyek semacam ini mangkrak hampir tak pernah
kodenya — melainkan perizinan pihak ketiga dan siklus hidup token. Dua hal itu
ditulis paling rinci di sini.

---

## Ringkasan: empat API, bukan dua

| API | Dapat apa | Auth | Ongkos pasang |
|---|---|---|---|
| **YouTube Data API v3** | Subscriber, total view, jumlah video, view/like/komentar per video | **API key** | Jam-jaman |
| **YouTube Analytics API** | Watch time, durasi tonton, retensi, traffic source, demografi, CTR | **OAuth 2.0** | Hari-harian |
| **Instagram Graph API** (insights) | Reach, profile view, follower count, insight per post | **OAuth 2.0 + App Review Meta** | Minggu-minggu |
| Instagram Graph API (basic) | Daftar media, caption, jumlah like/komentar | Sama seperti di atas | — |

**Urutan kerja yang disarankan:** YouTube Data API v3 dulu. Ia tidak bergantung
pada persetujuan siapa pun, bisa hidup hari ini, dan sekali jalan ia sudah
membuktikan seluruh rantai (cron → API → `POST /api/insights` → tabel → menu).
Instagram dan YouTube Analytics menyusul, karena jalur kritisnya ada di review
pihak ketiga yang tak bisa kamu percepat.

---

## Arsitektur — kenapa VPS, bukan shared hosting

```
VPS (cron)  ──1. ambil dari API YouTube/Instagram──┐
            ──2. refresh token sebelum kedaluwarsa─┤
                                                   ▼
                     POST /api/insights  (Authorization: Bearer <token>)
                                                   ▼
                      app.aipreneur.co.id → tabel insights → menu Insight
```

Tiga alasan, semuanya praktis:

1. **Rahasia API tidak pernah menyentuh shared hosting.** Client secret Google
   dan App Secret Meta cukup ada di satu tempat.
2. **Refresh token butuh cron yang andal.** Token Instagram mati di hari ke-60;
   kalau cron-nya terlewat, integrasinya diam-diam berhenti.
3. **Polanya sudah terbukti di repo ini.** `POST /api/scripts` +
   `SCRIPT_AGENT_TOKEN` sudah jalan. Tiru bentuknya, jangan bikin cara kedua.

Aplikasi Laravel-nya sengaja bodoh: hanya menyimpan dan menampilkan. Ia tidak
pernah memanggil API Google/Meta sendiri.

---

# BAGIAN 1 — YouTube Data API v3 (mulai dari sini)

Tanpa OAuth. Tanpa review. Cukup API key.

## 1.1 Buat project & API key

1. Buka <https://console.cloud.google.com/>
2. **Select a project → New Project.** Nama bebas, mis. `aipreneur-insight`.
3. **APIs & Services → Library** → cari **"YouTube Data API v3"** → **Enable**.
4. **APIs & Services → Credentials → Create Credentials → API key.**
5. Salin key-nya. **Klik Edit pada key itu**, lalu:
   - *API restrictions* → **Restrict key** → centang hanya **YouTube Data API v3**.
   - *Application restrictions* → **IP addresses** → isi IP VPS-mu.

> Membatasi key itu bukan formalitas. API key YouTube dikirim sebagai query
> string dan gampang bocor ke log. Key yang dibatasi ke satu API + satu IP
> nyaris tak berguna kalau bocor.

## 1.2 Cari channel ID

Bukan nama channel, bukan URL `@handle`. Yang dibutuhkan ID berawalan `UC…`:

```bash
curl -s "https://www.googleapis.com/youtube/v3/channels\
?part=id,snippet&forHandle=@NAMAHANDLE&key=API_KEY" | jq '.items[0].id'
```

## 1.3 Statistik channel

```bash
curl -s "https://www.googleapis.com/youtube/v3/channels\
?part=statistics&id=UCxxxxxxxx&key=API_KEY"
```

Balasannya:

```json
{ "items": [ { "statistics": {
  "viewCount": "1234567", "subscriberCount": "8900",
  "hiddenSubscriberCount": false, "videoCount": "142"
} } ] }
```

⚠️ `subscriberCount` **dibulatkan** oleh YouTube (mis. 8.900, bukan 8.947). Itu
perilaku YouTube, bukan bug. Jangan pakai angka ini untuk hitungan yang menuntut
presisi.

## 1.4 Statistik per video

Dua langkah — Data API tidak menyediakan "semua video" dalam satu panggilan.

**a. Ambil ID playlist "uploads" milik channel:**

```bash
curl -s "https://www.googleapis.com/youtube/v3/channels\
?part=contentDetails&id=UCxxxxxxxx&key=API_KEY"
# → items[0].contentDetails.relatedPlaylists.uploads  (biasanya UU… )
```

**b. Ambil isi playlist, lalu statistik videonya:**

```bash
# daftar video (maks 50 per halaman, pakai nextPageToken untuk lanjut)
curl -s "https://www.googleapis.com/youtube/v3/playlistItems\
?part=contentDetails&playlistId=UUxxxxxxxx&maxResults=50&key=API_KEY"

# statistik banyak video sekaligus — ID dipisah koma, maks 50
curl -s "https://www.googleapis.com/youtube/v3/videos\
?part=statistics,snippet&id=ID1,ID2,ID3&key=API_KEY"
```

## 1.5 Kuota — hitung sebelum menjadwalkan

Jatah default **10.000 unit/hari**. Biayanya:

| Panggilan | Unit |
|---|---|
| `channels.list` | 1 |
| `playlistItems.list` (50 video) | 1 |
| `videos.list` (50 video sekaligus) | 1 |
| `search.list` | **100** |

**Hindari `search.list`.** Sekali panggil = 100 unit; 100 panggilan sehari sudah
menghabiskan seluruh jatah. Jalur `uploads playlist` di atas melakukan hal yang
sama dengan biaya 1 unit.

Perhitungan nyata: 200 video, ditarik 2× sehari
= 2 × (1 + 4 playlistItems + 4 videos) = **18 unit/hari**. Sangat longgar.

---

# BAGIAN 2 — YouTube Analytics API (watch time & retensi)

Ini API yang **berbeda** dari Bagian 1, dengan **kolam kuota terpisah**. Butuh
OAuth karena datanya milik privat channel.

## 2.1 OAuth consent screen — dan jebakan 7 hari

1. **APIs & Services → Library** → Enable **"YouTube Analytics API"**.
2. **APIs & Services → OAuth consent screen**:
   - User type: **External**
   - Isi app name, support email, developer email
   - **Scopes** → Add → `https://www.googleapis.com/auth/yt-analytics.readonly`
     (tambahkan `yt-analytics-monetary.readonly` hanya kalau butuh data revenue)
   - **Test users** → tambahkan alamat Google pemilik channel

> ## ⚠️ JEBAKAN PALING MAHAL DI SELURUH PANDUAN INI
>
> Selama consent screen berstatus **"Testing"**, refresh token **mati dalam 7
> hari**. Integrasinya akan terlihat sempurna selama seminggu, lalu berhenti
> tanpa pesan yang jelas.
>
> **Kamu WAJIB menekan "Publish App"** di halaman OAuth consent screen.
>
> Untuk scope `yt-analytics.readonly` dengan pemakaian internal, publish
> biasanya tidak memicu verifikasi penuh Google. Kalaupun muncul layar
> peringatan "Google hasn't verified this app", pemilik channel cukup menekan
> *Advanced → Go to … (unsafe)* satu kali saat memberi izin.

## 2.2 Buat OAuth Client & ambil refresh token

1. **Credentials → Create Credentials → OAuth client ID → Desktop app.**
2. Simpan **Client ID** dan **Client Secret**.
3. Jalankan sekali di laptop (bukan di VPS — butuh browser):

```python
# pip install google-auth-oauthlib
from google_auth_oauthlib.flow import InstalledAppFlow

flow = InstalledAppFlow.from_client_secrets_file(
    'client_secret.json',
    scopes=['https://www.googleapis.com/auth/yt-analytics.readonly'],
)
creds = flow.run_local_server(port=0)
print('REFRESH TOKEN:', creds.refresh_token)   # <- ini yang disimpan di VPS
```

Login pakai akun **pemilik channel**, bukan akun lain.

## 2.3 Query analytics

```bash
curl -s "https://youtubeanalytics.googleapis.com/v2/reports\
?ids=channel==MINE\
&startDate=2026-06-01&endDate=2026-06-30\
&metrics=views,estimatedMinutesWatched,averageViewDuration,subscribersGained\
&dimensions=day" \
  -H "Authorization: Bearer ACCESS_TOKEN"
```

`ACCESS_TOKEN` berumur 1 jam, ditukar dari refresh token:

```bash
curl -s https://oauth2.googleapis.com/token \
  -d client_id=CLIENT_ID -d client_secret=CLIENT_SECRET \
  -d refresh_token=REFRESH_TOKEN -d grant_type=refresh_token
```

## 2.4 Dua sebab lain token mati mendadak

Catat, karena gejalanya membingungkan:

1. **Password akun Google diganti → Google mencabut SEMUA refresh token
   ber-scope YouTube.** Ganti password = insight berhenti. Harus ulangi 2.2.
2. **Refresh token tak terpakai 6 bulan → kedaluwarsa.** Tidak relevan kalau
   cron jalan harian, tapi relevan kalau integrasinya sempat dimatikan lama.

---

# BAGIAN 3 — Instagram Graph API

Bagian terberat. Jalur kritisnya **App Review Meta**, dan itu di luar kendalimu.

## 3.1 Prasyarat yang tidak bisa dilewati

Kerjakan ini dulu — tanpa ketiganya, tak ada yang bisa dimulai:

1. Akun Instagram harus **Business** atau **Creator**
   *(Instagram → Settings → Account type and tools → Switch to professional account)*
2. Akun itu harus **tertaut ke sebuah Facebook Page**
3. Kamu punya akses admin ke Page tersebut

Akun **pribadi tidak bisa** mengakses insights lewat API. Titik.

## 3.2 Buat Meta App

1. <https://developers.facebook.com/apps> → **Create App**
2. Use case: **Other** → tipe **Business**
3. Tambahkan produk **Instagram** → *API setup with Facebook login*
4. Catat **App ID** dan **App Secret**

## 3.3 Permission yang dibutuhkan

| Permission | Untuk apa |
|---|---|
| `instagram_basic` | Identitas akun, daftar media |
| `instagram_manage_insights` | **Insight** — reach, profile view, demografi |
| `pages_show_list` | Menemukan Page yang tertaut |
| `pages_read_engagement` | Membaca Page tersebut |

> ## ✅ JALAN PINTAS: kemungkinan besar kamu TIDAK butuh App Review
>
> Ini bagian yang paling sering disalahpahami, dan paling banyak membuang waktu
> orang. Dokumentasi Meta menyatakan sendiri:
>
> > *"Standard Access is intended for apps that will only be used by people who
> > have roles on them… If your app only serves your Instagram professional
> > account or an account you manage, Standard Access is all your app needs."*
>
> Artinya: **App Review hanya wajib kalau app-mu dipakai untuk akun milik ORANG
> LAIN.** Selama app masih Development mode, permission di atas sudah berfungsi
> penuh untuk akun yang punya peran **Admin / Developer / Tester** di app itu.
>
> Karena kamu cuma butuh insight akun sendiri:
>
> 1. Meta App → **App roles → Roles → Add People**
> 2. Tambahkan akunmu sebagai **Tester** (atau kamu memang sudah Admin)
> 3. Terima undangannya di <https://developers.facebook.com/requests>
> 4. Selesai — **lewati App Review sepenuhnya**
>
> Ini memangkas jalur kritis Instagram dari hitungan minggu jadi hitungan jam.
> Jangan ajukan App Review sebelum membuktikan jalur ini buntu.

## 3.4 Rantai token — bagian yang paling sering salah

```
Token user (1 jam)
      ↓ tukar
Token panjang (60 hari)          ← yang disimpan di VPS
      ↓ refresh tiap 50 hari
Token panjang baru (60 hari)
```

**a. Tukar jadi token panjang:**

```bash
curl -s "https://graph.facebook.com/v21.0/oauth/access_token\
?grant_type=fb_exchange_token\
&client_id=APP_ID&client_secret=APP_SECRET\
&fb_exchange_token=TOKEN_PENDEK"
```

**b. Cari Instagram Business Account ID:**

```bash
# 1) Page yang kamu kelola
curl -s "https://graph.facebook.com/v21.0/me/accounts?access_token=TOKEN_PANJANG"

# 2) IG account yang tertaut ke page itu
curl -s "https://graph.facebook.com/v21.0/PAGE_ID\
?fields=instagram_business_account&access_token=TOKEN_PANJANG"
```

**c. Ambil insight:**

```bash
# insight akun
curl -s "https://graph.facebook.com/v21.0/IG_USER_ID/insights\
?metric=reach,profile_views&period=day&access_token=TOKEN_PANJANG"

# insight per post
curl -s "https://graph.facebook.com/v21.0/IG_MEDIA_ID/insights\
?metric=reach,saved,likes,comments,shares&access_token=TOKEN_PANJANG"
```

## 3.5 Aturan refresh — jangan diserahkan pada ingatan

- Token panjang berumur **60 hari**
- **Baru bisa di-refresh setelah berumur 24 jam**
- Refresh **tiap 50 hari** lewat cron. Jangan tunggu hari ke-59.
- Kalau lewat 60 hari → token mati → **harus login ulang lewat browser**, tidak
  ada cara otomatis memulihkannya

```bash
# refresh (sekaligus cara mengecek token masih hidup)
curl -s "https://graph.facebook.com/v21.0/oauth/access_token\
?grant_type=fb_exchange_token\
&client_id=APP_ID&client_secret=APP_SECRET\
&fb_exchange_token=TOKEN_PANJANG_SEKARANG"
```

## 3.6 Nama metrik berubah-ubah

Meta beberapa kali mengganti dan mematikan nama metrik (`impressions` misalnya
sudah tidak tersedia di sebagian permukaan, digantikan `views`). **Jangan
menghardcode daftar metrik tanpa penanganan error.** Kalau satu metrik ditolak,
seluruh panggilan gagal.

Praktik amannya: minta metrik satu per satu atau tangkap error per metrik, dan
catat metrik yang ditolak ke log alih-alih membuat seluruh cron gagal.

---

# BAGIAN 4 — Sisi aplikasi

## 4.1 Rancangan tabel

```
insights
  id
  platform          string      # 'youtube' | 'instagram'
  akun              string      # channel id / ig user id
  tanggal           date        # tanggal data, bukan tanggal ambil
  metrik            json        # { followers, reach, views, watch_time, ... }
  timestamps

  unique (platform, akun, tanggal)   ← kunci; lihat 4.2
```

`metrik` sengaja `json`: nama metrik dari kedua platform berubah-ubah, dan
menambah kolom tiap kali metrik baru muncul akan jadi hutang migrasi tanpa
akhir. Yang perlu di-query dan dibandingkan lintas platform saja yang layak
dinaikkan jadi kolom sungguhan — nanti, kalau memang terbukti perlu.

## 4.2 Endpoint — tiru `ScriptIngestController`

`POST /api/insights`, gerbangnya bearer token, di luar `routes/web.php` supaya
tanpa sesi/CSRF. Salin pola dari
[`app/Http/Controllers/Api/ScriptIngestController.php`](../app/Http/Controllers/Api/ScriptIngestController.php),
termasuk dua hal yang sudah terbukti berguna di sana:

- **`hash_equals()`** untuk membandingkan token (waktu tetap), bukan `===`
- **503 vs 401 dibedakan**: 503 = token belum diisi di server, 401 = token
  dikirim tapi salah. Tanpa pembedaan ini, memasang di server jadi menebak-nebak

**Idempoten lewat `updateOrCreate` pada `(platform, akun, tanggal)`.** Cron bisa
jalan dua kali, atau kamu jalankan manual saat menguji — tanpa kunci unik itu,
satu hari bisa punya banyak baris dan grafiknya jadi ngawur. Ini pelajaran yang
sama dengan `ScriptIngestController` yang memakai ganti-paket per brand+tanggal.

## 4.3 Menu & hak akses

Menambah menu baru berarti menyentuh **tiga** tempat — kalau satu terlewat,
menunya tidak akan pernah muncul:

1. `User::MENUS` → daftarkan `'insight' => 'Insight'`
   *(kalau tidak, menu ini tak muncul di halaman Manajemen Akses)*
2. `User::MENU_ACCESS` → tentukan peran mana yang boleh (fallback saat tabel
   `role_menu_access` kosong)
3. `HandleInertiaRequests::share()` → tambahkan `'insight' => $user->canSee('insight')`
   pada peta `menus`, karena Sidebar membacanya dari situ

Lalu route-nya di dalam grup `['auth', EnsureMenuAccess::class]`.

---

# BAGIAN 5 — Cron di VPS

```bash
# Insight harian, 02:00 WIB (19:00 UTC hari sebelumnya)
0 19 * * *  cd /home/user/insight-agent && \
  python3 tarik_insight.py >> ~/insight.log 2>&1

# Refresh token Instagram — tiap 50 hari, jangan tunggu hari ke-60
0 3 1 */2 *  cd /home/user/insight-agent && \
  python3 refresh_token_ig.py >> ~/insight-token.log 2>&1
```

**`>> log 2>&1` bukan hiasan.** Cron yang gagal tidak meninggalkan jejak apa
pun; tanpa log, kamu baru sadar berminggu-minggu kemudian saat grafiknya datar.
Ini sudah pernah terjadi di repo ini dengan agen Script.

Pertimbangkan juga alarm sederhana: kalau `insights` tidak bertambah > 2 hari,
kirim notifikasi. Integrasi yang mati diam-diam jauh lebih berbahaya daripada
yang mati berisik.

---

# Checklist

**YouTube Data API v3** — bisa dikerjakan sekarang, tanpa menunggu siapa pun
- [ ] Project Google Cloud dibuat
- [ ] YouTube Data API v3 di-*enable*
- [ ] API key dibuat **dan dibatasi** (1 API + IP VPS)
- [ ] Channel ID (`UC…`) ditemukan
- [ ] Uji `channels.list` berhasil

**YouTube Analytics API**
- [ ] YouTube Analytics API di-*enable*
- [ ] OAuth consent screen diisi + scope `yt-analytics.readonly`
- [ ] **Consent screen sudah di-PUBLISH** (bukan Testing — jebakan 7 hari)
- [ ] OAuth Client (Desktop) dibuat
- [ ] Refresh token didapat & disimpan di VPS

**Instagram**
- [ ] Akun sudah Business/Creator
- [ ] Sudah tertaut Facebook Page
- [ ] Meta App dibuat, App ID + Secret dicatat
- [ ] Akun didaftarkan sebagai **Tester** (jalan pintas tanpa App Review, kalau
      cuma butuh akun sendiri)
- [ ] Token panjang didapat
- [ ] IG Business Account ID ditemukan
- [ ] Cron refresh 50-harian terpasang

**Aplikasi**
- [ ] Migrasi tabel `insights` (dengan unique key)
- [ ] `POST /api/insights` + token (tiru `ScriptIngestController`)
- [ ] Menu didaftarkan di **tiga** tempat (4.3)
- [ ] Cron VPS + logging terpasang

---

## Referensi resmi

- [Insights — Instagram Platform](https://developers.facebook.com/docs/instagram-platform/insights/)
- [Permissions Reference: `instagram_manage_insights`](https://developers.facebook.com/docs/permissions/reference/instagram_manage_insights/)
- [App Modes — kenapa Development mode cukup untuk akun sendiri](https://developers.facebook.com/docs/development/build-and-test/app-modes/)
- [App Roles — cara menambah Tester](https://developers.facebook.com/docs/development/build-and-test/app-roles/)
- [App Review — Instagram Platform](https://developers.facebook.com/docs/instagram-platform/app-review/)
- [YouTube Data API v3 — Authentication](https://developers.google.com/youtube/v3/guides/authentication)
- [YouTube Analytics & Reporting — OAuth 2.0](https://developers.google.com/youtube/reporting/guides/authorization)
- [YouTube Data API — Quota Calculator](https://developers.google.com/youtube/v3/determine_quota_cost)
