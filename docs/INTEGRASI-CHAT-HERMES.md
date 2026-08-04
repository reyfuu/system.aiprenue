# INTEGRASI CHAT WIDGET HERMES KE SYSTEM AIPRENEUR

Implementasi ini menanamkan widget chat Hermes berbasis iframe dashboard ke layout global Laravel-Inertia.

## Perubahan yang dilakukan

- Menambahkan komponen:
  - `resources/js/Components/HermesChatWidget.vue`

- Komponen menampilkan tombol floating chat di kanan bawah yang membuka iframe:
  - URL: `https://hermes.aipreneur.co.id/`
  - Menggunakan ikon toggle open/close.
  - Container ukuran default: `w-96` x `h-[500px]`.

- Memasang widget ke layout global pada:
  - `resources/js/Layout.vue`

- Menghapus widget chat inline lama (custom API `/hermes/chat`) dari `Layout.vue` agar tidak duplikasi UI.

## Catatan

- Widget menampilkan untuk user dengan `daily_report` menu aktif (`page.props.auth?.user?.menus?.daily_report === true`).
- Kalau butuh, akses ke widget bisa dipersempit/luaskan dengan menyesuaikan kondisi `v-if`.
