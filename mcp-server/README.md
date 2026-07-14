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

Semua pakai URL + header `Authorization: Bearer <MCP_TOKEN>`.

- **Claude** (app/claude.ai): Settings → Connectors → *Add custom connector* → URL `https://mcp.domainkamu.com/mcp`.
- **ChatGPT** (HP/web): Settings → Connectors (developer mode) → remote MCP server → URL yang sama.
- **Hermes Agent**: tambahkan sebagai MCP server di konfigurasinya (URL + token).

## Catatan keamanan
- **Selalu set `MCP_TOKEN`** (acak, panjang) di produksi. Tanpa token = terbuka.
- MCP server konek DB pakai kredensial di `.env` — jangan commit `.env` (sudah di `.gitignore`).
- Tool tulis (`create_task`) bypass logika/validasi Laravel (insert langsung). Untuk produksi, pertimbangkan arahkan lewat API Laravel + OAuth.

## Roadmap
- Tools tambahan: update/move/complete task, baca pipeline & omzet, mindmap.
- OAuth 2.1 (buat connector ChatGPT publik yang paling mulus).
- Routing lewat API Laravel (hormati validasi & audit) alih-alih DB langsung.
