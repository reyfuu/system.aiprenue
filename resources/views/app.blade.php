{{-- Root template Inertia: satu-satunya blade halaman. Semua UI dirender Vue. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>System AI Preneur</title>
    {{-- Hostinger: hanya build-test/ yang bisa diakses web server, build/ tidak.
         Jadi kita loading asset langsung dari build-test tanpa @vite. --}}
    <link rel="preload" as="style" href="/build-test/assets/app-BQNs9gAw.css" />
    <link rel="preload" as="style" href="/build-test/assets/app-KM5RKyGl.css" />
    <link rel="stylesheet" href="/build-test/assets/app-BQNs9gAw.css" />
    <link rel="stylesheet" href="/build-test/assets/app-KM5RKyGl.css" />
    <script type="module" src="/build-test/assets/app-246s_47R.js"></script>
    @inertiaHead
</head>
<body class="bg-brand-50 text-slate-800 min-h-screen">
    {{-- Titik mount: Inertia render komponen halaman di sini --}}
    @inertia
</body>
</html>
