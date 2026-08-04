# INTEGRASI CHAT WIDGET HERMES KE SYSTEM AIPRENEUR

Implementasi ini menanamkan widget chat Hermes berbasis **WebSocket JSON-RPC** ke layout global Laravel-Inertia.

## Perubahan yang dilakukan

- Menambahkan komponen:
  - `resources/js/Components/HermesChatWidget.vue`

- Komponen menampilkan tombol floating chat di kanan bawah yang membuka panel chat (embed WS):
  - Endpoint WebSocket: `wss://hermes.aipreneur.co.id/ws`
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
