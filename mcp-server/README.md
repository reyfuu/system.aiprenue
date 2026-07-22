# Pipeline MCP Server

Satu **MCP server standar** untuk System AI Preneur. Dipakai bersama oleh **ChatGPT**, **Claude**, dan **Hermes Agent** — ketiganya bicara protokol MCP, jadi cukup satu server + satu URL.

- **Transport:** Streamable HTTP (stateless) — `POST /mcp`
- **Data:** langsung ke MariaDB `pipeline`
- **Auth:** bearer token (`MCP_TOKEN`)

## Tools (v1)

| Tool | Fungsi |
|------|--------|
| `list_boards` | Daftar board kanban + jumlah task |
| `list_tasks` | Task aktif dalam satu board (`board` = key) |
| `create_task` | Buat task baru (`board`, `title`, `column?`) |

> Semua terverifikasi lokal via `test-client.js` (handshake, list, create, auth reject).

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
   Tool `list_boards`, `list_tasks`, `create_task`, `update_task` siap dipakai.

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
- Tool tulis (`create_task`/`update_task`) bypass validasi Laravel (insert langsung). Batas kerusakan: tabel kanban.

## Roadmap
- Rate limit + audit log per tool call.
- Tools tambahan: move/complete task, baca pipeline & omzet, mindmap.
- Routing lewat API Laravel (hormati validasi & audit) alih-alih DB langsung.
