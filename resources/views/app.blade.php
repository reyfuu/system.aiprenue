{{-- Root template Inertia: satu-satunya blade halaman. Semua UI dirender React. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    {{-- Encoding & viewport standar --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Token CSRF: Inertia kirim otomatis lewat cookie XSRF-TOKEN, ini cadangan --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- <title inertia> agar judul bisa diganti per halaman via <Head> --}}
    <title inertia>System AI Preneur</title>
    {{-- Preamble React Refresh (wajib sebelum @vite di mode dev) --}}
    @viteReactRefresh
    {{-- Bundel CSS + entry React Inertia --}}
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    {{-- Inertia sisipkan <title>/<meta> dari <Head> tiap halaman --}}
    @inertiaHead
</head>
<body class="bg-brand-50 text-slate-800 min-h-screen">
    {{-- Titik mount: Inertia render komponen halaman di sini --}}
    @inertia
</body>
</html>
