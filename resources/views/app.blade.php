{{-- Root template Inertia: satu-satunya blade halaman. Semua UI dirender Vue. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>System AI Preneur</title>
    {{-- Pemuatan aset dibedakan per environment:
         - Lokal (APP_ENV=local): pakai @vite normal. Kalau `npm run dev` jalan
           (ada public/hot) otomatis HMR, kalau tidak baca public/build/manifest.json.
           Tanpa cabang ini, perubahan Vue di laptop tidak pernah kelihatan karena
           browser selalu memuat file build-test yang di-hardcode.
         - Produksi (Hostinger): hanya build-test/ yang bisa diakses web server,
           build/ tidak. Jadi asset dimuat langsung dari build-test tanpa @vite.
           Nama file di bawah HARUS disamakan dengan hasil `npm run build` terbaru
           (lihat public/build/manifest.json) setiap kali aset di-deploy. --}}
    @if (app()->environment('local'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="preload" as="style" href="/build-test/assets/app-BBjW8YVk.css" />
        <link rel="preload" as="style" href="/build-test/assets/app-DYwVO9P8.css" />
        <link rel="stylesheet" href="/build-test/assets/app-BBjW8YVk.css" />
        <link rel="stylesheet" href="/build-test/assets/app-DYwVO9P8.css" />
        <script type="module" src="/build-test/assets/app-BrVLyPCU.js"></script>
    @endif
    @inertiaHead
</head>
<body class="bg-brand-50 text-slate-800 min-h-screen">
    {{-- Titik mount: Inertia render komponen halaman di sini --}}
    @inertia
</body>
</html>
