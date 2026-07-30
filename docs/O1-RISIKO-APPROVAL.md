# O1-Risiko & Approval — Sistem AI Preneur

Register risiko, kebutuhan tools/infrastruktur, dan daftar keputusan yang harus mendapat approval Gilang.

---

## 1. Register Risiko

### Risiko teknis

| ID | Risiko | Probabilitas | Dampak | Mitigasi | Status |
|---|---|---|---|---|---|
| RT1 | **SQLite dev → MariaDB prod tidak kompatibel** | Rendah | Tinggi | Semua migrasi pakai sintaks standar (no `WAL`-specific); tes di SQLite dan staging MariaDB | ✅ Termitigasi |
| RT2 | **Shared hosting terbatas** (tidak bisa install Node, cron, Redis, supervisor) | Pasti | Sedang | Arsitektur tanpa queue/cron; build aset di laptop; upload `public/build/`; backup via shell script + cron hosting | ✅ Termitigasi |
| RT3 | **Kurs USD/IDR API down** | Sedang | Rendah | Fallback Rp 16.000 (hardcoded); cache 12 jam; log warning | ✅ Termitigasi |
| RT4 | **Label warna dari DB tidak muncul di Tailwind** | Sedang | Sedang | Safelist `@source inline(...)` di `app.css`; tambah warna baru → rebuild safelist | ✅ Termitigasi |
| RT5 | **Data Insight tidak terkirim (VPS cron gagal)** | Sedang | Sedang | Upsert idempoten (cron re-run aman); monitoring VPS agent health; alert Telegram jika 2x berturut-turut gagal | ⏳ Perlu monitoring |
| RT6 | **Audit log tabel membengkak** | Tinggi | Rendah | Indeks + policy purge setelah 6 bulan; query selalu pakai filter rentang tanggal; pagination | ⏳ Perlu policy purge |
| RT7 | **Upload video gagal di VPS agent** | Sedang | Sedang | Status tracking + retry button di UI; error message dari agent disimpan | ⏳ Perlu implementasi |
| RT8 | **Concurrent edit kartu — data overwrite** | Rendah | Sedang | `completed_at` stempel pertama freeze; optimistic UI + refresh on conflict | ✅ Termitigasi |
| RT9 | **Dependency PHP/Node EOL** | Rendah | Rendah | Versi terkunci di `composer.json` & `package.json`; upgrade saat Laravel rilis major baru | ✅ Termitigasi |

### Risiko bisnis

| ID | Risiko | Probabilitas | Dampak | Mitigasi | Status |
|---|---|---|---|---|---|
| RB1 | **Team tidak adopsi sistem** — tetap pakai Google Sheets | Sedang | Tinggi | Onboarding documented, UI familiar (mirip Trello), transisi bertahap (paralel dulu) | ⏳ Perlu strategi change management |
| RB2 | **Data migration from Sheets incomplete/wrong** | Rendah | Tinggi | Import SQL dengan script validasi; dry-run dulu; bandingkan total omzet | ✅ Termitigasi |
| RB3 | **Register terbuka disalahgunakan** | Rendah | Sedang | User baru = staff (hak terbatas); hanya owner/it yang bisa ubah role; bisa ditutup (non-goal: moderation) | ✅ Termitigasi |
| RB4 | **Staff bisa lihat data sensitif via Inertia props source** | Rendah | Kritis | Props disaring di server; `quarterStats` null untuk staff; data sensitif tidak pernah dikirim | ✅ Termitigasi |
| RB5 | **Gilang (owner) terkunci dari sistem** | Sangat rendah | Kritis | `User::canSee()` selalu return true untuk owner; tidak bisa dikunci lewat halaman Akses | ✅ Termitigasi |
| RB6 | **Production data loss (no backup)** | Rendah | Kritis | Backup database harian via cron; restore script documented | ⏳ Perlu Fase 7 |

### Risiko keamanan

| ID | Risiko | Probabilitas | Dampak | Mitigasi | Status |
|---|---|---|---|---|---|
| RS1 | **API token bocor** | Rendah | Tinggi | Token di `.env` (tidak di repo); `hash_equals` timing-safe; rotate token mudah (ganti env) | ✅ Termitigasi |
| RS2 | **Brute force login** | Sedang | Sedang | Rate limit login: 5/menit setelah Fase 7; session lifetime pendek | ⏳ Perlu Fase 7 |
| RS3 | **Session hijacking** | Rendah | Tinggi | HttpOnly cookie, `same_site: lax`, session expire; HTTPS wajib | ⏳ HTTPS perlu Fase 7 |
| RS4 | **SQL injection via raw query** | Rendah | Kritis | Semua query lewat Eloquent; tidak ada `DB::raw()` dengan input user | ✅ Termitigasi |
| RS5 | **XSS via deskripsi/komentar** | Rendah | Sedang | Vue auto-escape `{{ }}`; validasi server (max length, no HTML tags yang diizinkan) | ✅ Termitigasi |
| RS6 | **File upload: executable shell di lampiran** | Rendah | Kritis | Validasi MIME; disk `public` tidak menjalankan PHP; ekstensi white-list | ✅ Termitigasi |
| RS7 | **IDOR: user akses data user lain** | Rendah | Tinggi | KPI rapor orang: server saring `where('assigned_to', auth()->id())`; OKR hanya owner+manager | ✅ Termitigasi |
| RS8 | **No HTTPS di shared hosting** | Sedang | Tinggi | Pasang SSL via cPanel/Cloudflare; force HTTPS di `.htaccess` | ⏳ Perlu Fase 7 |

---

## 2. Kebutuhan Tools & Infrastruktur

### Sudah tersedia

| Tool | Fungsi | Konfigurasi |
|---|---|---|
| **Laravel 13 + PHP 8.5** | Backend framework | `composer.json` |
| **MariaDB** (shared hosting) | Database produksi | `.env` production |
| **SQLite** (dev) | Database development | WAL mode |
| **Vite** | Build aset | `vite.config.js` |
| **Node.js** (laptop) | Build Vue/Tailwind | `package.json` |
| **DomPDF** | PDF report | `barryvdh/laravel-dompdf` |
| **Chart.js + vue-chartjs** | Grafik Pembukuan | `package.json` |
| **simple-mind-map** | Mindmap editor | `package.json` |
| **Git + GitHub** | Version control | Repo existing |
| **open.er-api.com** | Kurs USD/IDR | API gratis, cache 12 jam |
| **Node.js MCP** | AI tool akses OKR | `/mcp-server/` |

### Butuh disiapkan

| Tool | Fungsi | Fase | Pemilik |
|---|---|---|---|
| **SSL Certificate** | HTTPS untuk produksi | F7 | DevOps / hosting admin |
| **Slack webhook** | Alert error → Slack | F7 | DevOps |
| **Cloudflare** (opsional) | CDN + SSL + DDoS protection | F7 | DevOps |
| **VPS agent** (Python/Bash) | Upload YouTube + Instagram OAuth | F6 | Backend / DevOps |
| **YouTube Data API v3** | OAuth + upload video | F6 | Gilang (Google Cloud Console) |
| **Meta App** (Instagram) | OAuth + Instagram upload | Future | Gilang (Meta Developer) |
| **Cron job** (shared hosting) | Backup database harian | F7 | DevOps |
| **Sentry / Telescope** (opsional) | Error tracking | F7 | Backend |

### Kredensial yang perlu dikelola

| Kredensial | Lokasi | Dibuat oleh |
|---|---|---|
| `APP_KEY` | `.env` | `php artisan key:generate` |
| `DB_PASSWORD` (MariaDB) | `.env` production | Hosting admin |
| `SCRIPT_AGENT_TOKEN` | `.env` + GitHub Secrets | Gilang / DevOps |
| `INSIGHT_AGENT_TOKEN` | `.env` + VPS env | Gilang / DevOps |
| `LOG_SLACK_WEBHOOK_URL` | `.env` | Gilang (Slack admin) |
| YouTube OAuth client ID + secret | `.env` / VPS agent | Gilang (Google Cloud) |

---

## 3. Keputusan yang Membutuhkan Approval Gilang

### A. Scope & Prioritas

| # | Keputusan | Konteks | Opsi | Rekomendasi |
|---|---|---|---|---|
| A1 | **Prioritas Fase 6 vs Fase 7** | F6 (Upload + Audit) vs F7 (Hardening) — mana duluan? | F6 dulu → F7, atau paralel, atau F7 dulu (security-first) | F6 dulu (audit log penting untuk compliance), F7 segera setelahnya |
| A2 | **Upload: YouTube saja atau TikTok+IG juga?** | YouTube lebih mudah (OAuth API matang), TikTok/IG butuh Meta App approval | YouTube dulu, TikTok+IG "soon" | YouTube dulu via VPS agent; TikTok/IG tetap template |
| A3 | **Dashboard filter quarter — perlu?** | Saat ini hanya filter bulan; quarter akan berguna untuk OKR alignment | Ya, tambah di F6 | Ya |
| A4 | **Notifikasi tambahan di luar OKR?** | Saat ini hanya notifikasi OKR. Deadline kartu di luar OKR belum ada notif | Tambah notifikasi untuk deadline kartu non-OKR | Bisa tunda — fokus ke OKR + audit dulu |
| A5 | **Open registration tetap dibuka?** | Saat ini siapa pun bisa register dan jadi staff. Disalahgunakan? | Tutup (invite-only) / Biarkan (by design) | Biarkan — risiko rendah, onboarding mudah |

### B. Budget & Infrastruktur

| # | Keputusan | Konteks | Opsi | Rekomendasi |
|---|---|---|---|---|
| B1 | **VPS untuk upload agent: server baru atau pakai existing?** | Upload YouTube butuh server untuk OAuth callback + upload processing | Pakai VPS yang sama dengan Insight agent / Buat VPS baru kecil ($5/bln) | Pakai VPS existing kalau ada kapasitas; kalau tidak, VPS $5/bln cukup |
| B2 | **Cloudflare: perlu atau tidak?** | Shared hosting kadang lambat; Cloudflare = CDN + SSL + DDoS | Pasang (free tier cukup) / Tidak | Pasang — free tier cukup, SSL gratis, performance bonus |
| B3 | **Slack workspace: perlu channel khusus?** | Untuk alert error → monitoring | Buat `#system-alert` channel | Ya, agar tidak bercampur chat tim |
| B4 | **Sentry / error tracking: perlu?** | Saat ini hanya log file; tanpa alert realtime kalau error 500 | Pasang Sentry (free tier) / Pakai Slack webhook saja / Log file saja | Sentry free tier sudah cukup baik; alternatif: Laravel Telescope di dev |

### C. Keamanan & Kepatuhan

| # | Keputusan | Konteks | Opsi | Rekomendasi |
|---|---|---|---|---|
| C1 | **Session lifetime: 15 menit atau lebih?** | Fase 7 rencana pasang 15 menit idle timeout. Terlalu pendek bisa mengganggu UX | 15 menit / 30 menit / 60 menit / tanpa batas (sekarang) | 30 menit — seimbang antara keamanan dan UX |
| C2 | **Rate limit register: berapa?** | Mencegah spam register | 3/jam / 5/jam / 10/jam / tidak perlu | 3/jam — cukup restriktif, tidak menghambat user asli |
| C3 | **Login attempt lockout: perlu?** | Brute force protection di luar rate limit | 5 gagal → lock 15 menit / Tidak perlu (cukup rate limit) | Rate limit saja cukup; lockout bisa ganggu user lupa password |
| C4 | **Audit log retention: berapa lama?** | Data audit log akan bertambah cepat. Butuh policy purge | 3 bulan / 6 bulan / 12 bulan / tanpa purge (archive) | 6 bulan di main table, archive sebagai file JSON untuk data lama |
| C5 | **CSP header: strict atau loose?** | Content-Security-Policy bisa merusak UI kalau terlalu strict | Strict (whitelist minimal) / Loose (self + unsafe-inline) / Tidak pasang | Loose dulu (self), lalu tighten bertahap sambil tes UI |

### D. Go-Live & Transisi

| # | Keputusan | Konteks | Opsi | Rekomendasi |
|---|---|---|---|---|
| D1 | **Tanggal go-live target?** | Fase 7 selesai ≈ 3-5 minggu dari sekarang | Tentukan tanggal spesifik / Fleksibel (setelah PT selesai) | Tentukan setelah F6 selesai + PT lewat |
| D2 | **Migrasi data: replace atau paralel?** | Data dari Google Sheets harus masuk ke sistem. Strategi? | Big bang (cutoff, semua di sistem) / Paralel (Sheets + sistem bareng 1-2 minggu) | Paralel 2 minggu — tim bisa validasi data sambil tetap kerja di Sheets |
| D3 | **Onboarding: training formal atau self-service?** | Tim perlu belajar sistem baru | Training 1 sesi / Dokumentasi + video tutorial / Belajar sendiri | Training 1 sesi (30-60 menit) + dokumentasi tertulis — cukup |
| D4 | **Rollback plan: bagaimana kalau sistem gagal?** | Harus ada fallback kalau sistem down | Kembali ke Sheets / Maintain Sheets read-only sebagai backup / Tidak perlu | Maintain Sheets sebagai read-only backup selama 2 minggu pertama |

---

## 4. Tanda Tangan Virtual

| Area | Nama | Status |
|---|---|---|
| **Scope & prioritas** | Gilang | ⬜ Review |
| **Budget & infrastruktur** | Gilang | ⬜ Review |
| **Keamanan** | Gilang | ⬜ Review |
| **Go-live timeline** | Gilang | ⬜ Review |
| **Risk acceptance** (risiko yang tidak dimitigasi) | Gilang | ⬜ Review |

---

## 5. Ringkasan untuk Gilang

**Apa yang sudah jadi:**
- 16 modul aplikasi siap pakai (Dashboard, Pipeline, Kanban, OKR, KPI, Pembukuan, Insight, Script, Content, Order, Absensi, Mindmap, Tracking, Upload-template, User, Akses).
- 275 tes otomatis, build frontend sukses, otorisasi 5 peran ditegakkan di server.
- MCP server untuk AI tools akses strategi OKR.

**Apa yang sedang dikerjakan (Fase 6):**
- Audit log (rekam semua perubahan di sistem).
- Upload YouTube (draft → VPS agent → YouTube).
- Dashboard enhancement (filter quarter).

**Apa yang masih butuh (Fase 7):**
- SSL + security headers.
- Monitoring (Slack alert error).
- Backup database otomatis.
- Penetration testing.

**3 keputusan paling urgent buat Gilang:**
1. Upload: YouTube dulu saja, atau sekalian TikTok+Instagram? (rekomendasi: YouTube dulu)
2. Session timeout: berapa menit? (rekomendasi: 30 menit)
3. Go-live: langsung atau paralel dengan Sheets dulu? (rekomendasi: paralel 2 minggu)
