<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembukuan — Pipeline FK-AI Preneur</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/scripts/pembukuan.jsx'])
</head>
<body class="bg-brand-50 text-slate-800 min-h-screen">

@include('partials.sidebar')

<div class="md:ml-56">
    <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
        <div class="px-6 py-5 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">PEMBUKUAN</h1>
                <p class="text-brand-100 text-sm">Pemasukan, pengeluaran &amp; inventaris</p>
            </div>
            <a href="{{ $payload['reportUrl'] }}" target="_blank"
               class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6L19 8.4V18a2 2 0 01-2 2z"/></svg>
                Export PDF
            </a>
        </div>
    </header>

    {{-- React mount point (react-chartjs-2) --}}
    <div class="px-6 py-6">
        <div id="pembukuan-root" data-payload="{{ json_encode($payload) }}"></div>
    </div>
</div>
</body>
</html>
