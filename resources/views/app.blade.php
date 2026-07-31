{{-- Root template Inertia: satu-satunya blade halaman. Semua UI dirender Vue. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>System AI Preneur</title>
    {{-- Aset dimuat via @vite (baca public/build/manifest.json) untuk semua
         environment. /build/ web-accessible di Hostinger, jadi tak perlu lagi
         hardcode nama file build-test. Deploy cukup: git pull + view:clear.
         Lokal: kalau `npm run dev` jalan (ada public/hot) otomatis HMR. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-brand-50 text-slate-800 min-h-screen">
    {{-- Titik mount: Inertia render komponen halaman di sini --}}
    @inertia
</body>
</html>
