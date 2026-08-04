# INTEGRASI CHAT WIDGET HERMES KE SYSTEM AIPRENEUR

Implementasi ini menanamkan widget chat Hermes berbasis **WebSocket JSON-RPC** ke layout global Laravel-Inertia.

## Perubahan yang dilakukan

- Menambahkan komponen:
  - `resources/js/Components/HermesChatWidget.vue`

- Komponen menampilkan tombol floating chat di kanan bawah yang membuka panel chat (embed WS):
  - Endpoint WebSocket: `wss://hermes.aipreneur.co.id/ws` (proxy kompatibel ke `/api/ws`)
  - Method inisialisasi default: `init`
  - Method kirim pesan default: `chat`
  - Protokol: JSON-RPC 2.0 (`jsonrpc`, `id`, `method`, `params`)
  - Container ukuran default: `w-96` x `h-[500px]`

- Menyediakan fallback otomatis ke endpoint HTTP ketika WS gagal:
  - `POST /hermes/chat`

- Memasang widget ke layout global pada:
  - `resources/js/Layout.vue`

- Menghapus widget chat inline lama (jika masih ada) dari `Layout.vue` agar tidak terjadi duplikasi UI.

## Catatan

- Widget ditampilkan untuk user dengan menu `daily_report` aktif (`page.props.auth?.user?.menus?.daily_report === true`).
- Akses bisa dipersempit/luaskan dengan menyesuaikan kondisi `v-if` sesuai kebutuhan role.

## Konfigurasi lingkungan (Vite)

Tambahkan variabel berikut pada `.env` agar endpoint dan method bisa dikustom:

- `VITE_HERMES_WS_URL=wss://hermes.aipreneur.co.id/ws`
- `VITE_HERMES_WS_INIT_METHOD=init`
- `VITE_HERMES_WS_CHAT_METHOD=chat`
- `VITE_HERMES_CHAT_URL=/hermes/chat`

## Jalur koneksi yang harus dipakai

- Koneksi chat realtime harus mengarah ke WebSocket endpoint:
  - `wss://hermes.aipreneur.co.id/ws`
- Jangan memanggil root `https://hermes.aipreneur.co.id/` untuk chat realtime.

## Operasional service Hermes (VPS)

Jalankan servis Hermes dashboard di VPS (atau lokal saat debug) dengan:

```bash
hermes dashboard --host 127.0.0.1 --port 9119 --no-open --skip-build &
```

Pastikan:

- prosesnya jalan (TCP 9119 aktif),
- reverse proxy mengarah ke endpoint websocket `/ws` dari domain publik `wss://hermes.aipreneur.co.id/ws`,
- `HERMES_AGENT_URL` di app Laravel mengarah ke base HTTP VPS (`https://hermes.aipreneur.co.id`),
  - `HERMES_AGENT_CHAT_PATH` sesuai path chat yang benar (contoh: `/api/chat`).

## Checklist verifikasi (jika muncul error koneksi)

1. Jalankan / cek service Hermes:

```bash
hermes dashboard --host 127.0.0.1 --port 9119 --no-open --skip-build &
lsof -i :9119 | grep LISTEN
```

2. Pastikan route WebSocket sudah benar ke `/ws`:

```bash
curl -i https://hermes.aipreneur.co.id/ws
```

3. Pastikan `.env` app Laravel memiliki nilai:

- `HERMES_AGENT_URL=https://hermes.aipreneur.co.id`
- `HERMES_AGENT_CHAT_PATH=/chat` (atau path HTTP chat yang valid dari VPS; boleh isi beberapa path dipisah koma, contoh: `/api/chat,/chat`)
  - `VITE_HERMES_WS_URL=wss://hermes.aipreneur.co.id/ws`

> Catatan penting:
>
> Endpoint `/chat` di Hermes dashboard biasanya dipakai untuk WebSocket (GET/upgrade), sehingga `POST /chat` bisa memberi 405.
> Kalau fallback HTTP dari Laravel menyala dan Hermes merespons `HTTP 405`, ubah `HERMES_AGENT_CHAT_PATH` ke endpoint POST yang valid (umumnya `/api/chat`) dan pastikan token `HERMES_AGENT_TOKEN` aktif.

4. `HTTP 405` biasanya berarti Hermes VPS tidak menerima method/path yang kamu panggil untuk route chat (biasanya karena path salah, bukan karena WebSocket `/ws`).
