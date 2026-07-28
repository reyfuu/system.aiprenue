# Pipeline MCP Server

Satu **MCP server standar** untuk System AI Preneur. Dipakai bersama oleh **ChatGPT**, **Claude**, dan **Hermes Agent** — ketiganya bicara protokol MCP, jadi cukup satu server + satu URL.

- **Transport:** Streamable HTTP (stateless) — `POST /mcp`
- **Data:** langsung ke MariaDB `pipeline`
- **Auth:** bearer token (`MCP_TOKEN`)

## Tools

Mulai v0.2, MCP mencakup seluruh modul internal System AI Preneur. Tool dibuat
eksplisit per fitur; tidak ada tool SQL generik.

**Lintas sistem**

| Tool | Fungsi |
|------|--------|
| `get_dashboard_summary` | Ringkasan Dashboard lintas modul |
| `list_users` | User dan role (tanpa data rahasia) |
| `list_access` | Matriks Manajemen Akses |
| `get_upload_status` | Status data terakhir dari Upload/ingest Insight |

**Kanban**

| Tool | Fungsi |
|------|--------|
| `list_boards` | Daftar board kanban + jumlah task |
| `list_tasks` | Task aktif dalam satu board (`board` = key) |
| `create_task` | Buat task baru (`board`, `title`, `column?`) |
| `update_task` | Ubah task by id (`deadline`/`column`/`done`/`title`) |

**Sales**

| Tool | Fungsi |
|------|--------|
| `list_pipeline_stages` | Daftar stage Sales |
| `list_deals` | Daftar/filter deal |
| `create_deal` | Buat deal |
| `update_deal` | Perbarui deal |

**OKR — menyusun & memantau strategi kuartalan**

| Tool | Fungsi |
|------|--------|
| `list_okr` | Objective + Key Result satu kuartal + realisasi & capaian (default kuartal berjalan). **Baca ini dulu** sebelum menyusun strategi. |
| `create_objective` | Buat Objective (goal kualitatif) untuk satu kuartal |
| `create_key_result` | Tambah KR terukur — `source`: `auto` (view/subscriber/omset dari data) · `manual` · `kartu` (dari task todolist tertaut yang selesai) |
| `link_task_to_kr` | Tautkan task todolist ke KR bersumber `kartu` (langkah pencapaian goal) |

**Alur menyusun strategi lewat AI (Claude/ChatGPT):**
1. `list_okr` — lihat posisi kuartal ini (realisasi view/subscriber/omset nyata).
2. `create_objective` → `create_key_result` — susun goal & KR terukur.
3. Untuk KR `kartu`: `create_task` (board `todolist`) → `link_task_to_kr` — pecah goal jadi langkah. Menyelesaikan task menggerakkan angka KR otomatis.

> Realisasi metrik `auto` (view/subscriber/omset) memakai rumus **sama persis** dengan app Laravel (`OkrMetrics`): view = tayangan konten terbit di kuartal · subscriber = snapshot follower terakhir per akun · omset = pemasukan Pembukuan di kuartal. Angka MCP = angka halaman `/okr`.

> Tulisan langsung ke DB (bypass validasi Laravel), tapi `create_key_result` & `link_task_to_kr` menegakkan aturan yang sama: KR `auto` wajib punya metric, tautan kartu hanya board `todolist` + KR bersumber `kartu`.

> Kanban terverifikasi lokal via `test-client.js`; OKR terverifikasi via `tools/list` + `list_okr` live (angka realisasi cocok dengan `/okr`).

**Modul lain**

| Modul | Tool baca | Tool tulis |
|------|-----------|------------|
| Order | `list_orders` | `create_order` |
| Pembukuan | `list_finance`, `list_inventory` | `create_transaction` |
| Content | `list_content` | `create_content` |
| Script | `list_scripts` | `create_script` |
| Insight | `list_insights` | melalui API ingest yang sudah ada |
| Tracking | `list_tracking` | perubahan card lewat `update_task` |
| KPI Board | `list_kpi` | target tetap dikelola dari aplikasi |
| Absensi | `list_absences` | `create_absence` |
| Mindmap | `list_mindmaps` | editor visual tetap di aplikasi |

Tool baca yang mengembalikan daftar memakai batas maksimal 200 baris agar satu
permintaan MCP tidak memuat seluruh database tanpa sengaja.

## Jalankan lokal

```bash
cd mcp-server
cp .env.example .env      # isi DB + MCP_TOKEN
npm install
npm start                 # http://127.0.0.1:8765/mcp
npm test                  # jalankan test-client.js (server harus nyala)
```

## Deploy ke VPS (Ubuntu 24.04)

Asumsi ada domain, mis. `mcp.domainkamu.com` → A record ke IP VPS.

```bash
# 1. Node LTS
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs nginx

# 2. Taruh kode + deps
sudo mkdir -p /opt/pipeline-mcp && sudo chown $USER /opt/pipeline-mcp
# rsync/scp folder mcp-server/ ke /opt/pipeline-mcp
cd /opt/pipeline-mcp && npm install --omit=dev
cp .env.example .env   # isi: MCP_TOKEN acak panjang, DB_* ke MariaDB VPS
```

**systemd** — `/etc/systemd/system/pipeline-mcp.service`:

```ini
[Unit]
Description=Pipeline MCP Server
After=network.target mariadb.service

[Service]
WorkingDirectory=/opt/pipeline-mcp
ExecStart=/usr/bin/node server.js
Restart=always
EnvironmentFile=/opt/pipeline-mcp/.env
User=www-data

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload && sudo systemctl enable --now pipeline-mcp
```

**nginx** — `/etc/nginx/sites-available/mcp` (reverse proxy, penting: proxy buffering off untuk streaming):

```nginx
server {
    server_name mcp.domainkamu.com;
    location / {
        proxy_pass http://127.0.0.1:8765;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header Connection '';
        proxy_buffering off;      # wajib utk Streamable HTTP/SSE
        proxy_read_timeout 3600s;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/mcp /etc/nginx/sites-enabled/
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d mcp.domainkamu.com   # TLS otomatis
sudo nginx -t && sudo systemctl reload nginx
```

URL final: `https://mcp.domainkamu.com/mcp`

## Hubungkan client

Dua mekanisme auth, satu server:

- **OAuth 2.1** (ChatGPT & Claude hp/web) — UI mereka tak bisa kirim bearer statis, jadi
  server ini jadi authorization server sendiri (owner-tunggal, tanpa dependency). Owner
  login pakai `MCP_TOKEN` sebagai password. Wajib set `MCP_PUBLIC_URL` di produksi.
- **Bearer statis** (Claude Code, Hermes, `task.js`) — header `Authorization: Bearer <MCP_TOKEN>`.

### Claude (fokus utama — app/claude.ai, plan berbayar spt Max)
1. Claude → Settings → **Connectors** → **Add custom connector**.
2. Isi URL: `https://mcp.aipreneur.co.id/mcp`. Claude otomatis discovery + daftar sendiri (DCR).
3. Muncul halaman login server → masukkan **`MCP_TOKEN`** sebagai password → **Masuk**.
4. Selesai — connector sync ke semua device (web, iOS, Android, desktop).
   Seluruh tool System AI Preneur siap dipakai.

### Claude Code / Hermes
- Tambah sebagai MCP server dengan URL + header `Authorization: Bearer <MCP_TOKEN>`.

### ChatGPT (butuh plan yang mendukung MCP connector)
- Flow-nya identik (OAuth). Hanya tersedia di plan ChatGPT yang mengizinkan custom/remote
  MCP connector (Pro/Business/Enterprise). Plan tanpa fitur ini tidak bisa memakainya.

**Cek cepat OAuth (opsional):**
```bash
curl https://mcp.aipreneur.co.id/.well-known/oauth-protected-resource
node server.js --selftest   # verifikasi JWT + PKCE tanpa DB/HTTP
```

## Catatan keamanan
- **Selalu set `MCP_TOKEN`** (acak, panjang) di produksi. Kosong = terbuka **dan** OAuth mati.
- OAuth: access token = JWT HMAC (kunci diturunkan dari `MCP_TOKEN`), TTL 1 jam + refresh 30 hari.
  Authorization code in-memory (TTL 60 dtk) — restart server → login ulang sekali. Cukup utk owner-tunggal.
- MCP server konek DB pakai kredensial di `.env` — jangan commit `.env` (sudah di `.gitignore`).
- Tool tulis masih memakai koneksi DB langsung, tetapi memakai schema Zod,
  enum yang sama dengan aplikasi, query berparameter, dan tidak menyediakan SQL generik.

## Roadmap
- Rate limit + audit log per tool call.
- Mutasi lanjutan untuk Mindmap, KPI, Insight, dan persetujuan Absensi.
- Routing tool tulis lewat API Laravel agar seluruh validasi dan audit aplikasi
  menjadi satu sumber kebenaran.
