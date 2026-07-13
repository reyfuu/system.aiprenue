@php
    use App\Models\Pipeline;
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $progressDot = [
        'script' => 'bg-purple-500', 'editing' => 'bg-sky-500', 'progress' => 'bg-brand-600',
        'pending' => 'bg-amber-500', 'done' => 'bg-emerald-500',
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — Pipeline FK-AI Preneur</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-brand-50 text-slate-800 min-h-screen">

@include('partials.sidebar')

<div class="md:ml-56">
    <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
        <div class="px-6 py-5">
            <h1 class="text-2xl font-bold tracking-tight">DASHBOARD</h1>
            <p class="text-brand-100 text-sm">Ringkasan sistem AI Preneur — kurs 1 USD = {{ $rp($rate) }}</p>
        </div>
    </header>

    <div class="px-6 py-6 space-y-6">

        {{-- Quick stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                <p class="text-xs text-slate-500 font-medium">Total Entri</p>
                <p class="text-2xl font-bold text-brand-700 mt-1">{{ $total }}</p>
            </div>
            <div class="bg-gradient-to-br from-brand-600 to-brand-700 rounded-2xl shadow-sm p-4 text-white">
                <p class="text-xs text-brand-100 font-medium">Grand Omzet (IDR)</p>
                <p class="text-2xl font-bold mt-1">{{ $rp($grandIdr) }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                <p class="text-xs text-slate-500 font-medium">Lunas</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $lunas }}<span class="text-sm text-slate-400 font-medium"> / {{ $total }}</span></p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                <p class="text-xs text-slate-500 font-medium">Outstanding</p>
                <p class="text-2xl font-bold text-red-600 mt-1">{{ $outstanding }}</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">

            {{-- Pipeline --}}
            <a href="{{ route('pipelines.index') }}" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-slate-700">Pipeline</h2>
                    <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                </div>
                <p class="text-3xl font-bold text-brand-700">{{ $total }} <span class="text-sm text-slate-400 font-medium">entri</span></p>
                <div class="mt-4 space-y-2">
                    @foreach (Pipeline::CATEGORIES as $ck => $cv)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">{{ $cv }}</span>
                            <span class="font-semibold text-slate-700">{{ $perCategory[$ck] ?? 0 }}</span>
                        </div>
                    @endforeach
                </div>
            </a>

            {{-- Kanban --}}
            <a href="{{ route('pipelines.kanban') }}" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-slate-700">Kanban</h2>
                    <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                </div>
                <p class="text-3xl font-bold text-brand-700">{{ $done }} <span class="text-sm text-slate-400 font-medium">/ {{ $total }} done</span></p>
                <div class="mt-4 space-y-2.5">
                    @foreach (Pipeline::PROGRESS as $pk => $pv)
                        @php $c = $perProgress[$pk] ?? 0; $pct = $total ? round($c / $total * 100) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="flex items-center gap-1.5 text-slate-500">
                                    <span class="w-2 h-2 rounded-full {{ $progressDot[$pk] }}"></span>{{ $pv }}
                                </span>
                                <span class="font-semibold text-slate-700">{{ $c }}</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-brand-50 overflow-hidden">
                                <div class="h-full {{ $progressDot[$pk] }}" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </a>

            {{-- Pembukuan --}}
            <a href="{{ route('pembukuan.index') }}" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-slate-700">Pembukuan</h2>
                    <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                </div>
                <p class="text-3xl font-bold text-brand-700">{{ $rp($grandIdr) }}</p>
                <p class="text-xs text-slate-400 mt-1">Grand total omzet</p>
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Omzet IDR</span>
                        <span class="font-semibold text-slate-700">{{ $rp($totalIdr) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Omzet USD</span>
                        <span class="font-semibold text-slate-700">$ {{ number_format($totalUsd, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Kurs USD→IDR</span>
                        <span class="font-semibold text-slate-700">{{ $rp($rate) }}</span>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>
</body>
</html>
