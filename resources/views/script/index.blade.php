@php
    // ponytail: placeholder template — isi file dikirim oleh Hermes agent ke public/scripts/*.
    $folders = [
        ['name' => 'Script FK', 'path' => 'scripts/fk', 'count' => null],
        ['name' => 'Script Rave Tailor', 'path' => 'scripts/rave-tailor', 'count' => null],
        ['name' => 'Script Raveloux', 'path' => 'scripts/raveloux', 'count' => null],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Script — Pipeline FK-AI Preneur</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-brand-50 text-slate-800 min-h-screen" x-data="{ menu: null }">

@include('partials.sidebar')

<div class="md:ml-56">
    <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
        <div class="px-6 py-5 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">SCRIPT</h1>
                <p class="text-brand-100 text-sm">Kumpulan folder naskah</p>
            </div>
            <button class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Folder Baru
            </button>
        </div>
    </header>

    <div class="px-6 py-6">
        {{-- sort bar (template) --}}
        <div class="flex items-center gap-1.5 text-xs text-slate-500 mb-4">
            <span class="font-semibold text-slate-600">Nama</span>
            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
        </div>

        {{-- grid folder --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            @foreach ($folders as $f)
                <div class="group relative bg-white hover:bg-brand-50 border border-brand-100 hover:border-brand-200 rounded-xl px-4 py-3.5 flex items-center gap-3 cursor-pointer transition shadow-sm">
                    <svg class="w-6 h-6 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M3 7a2 2 0 012-2h4l2 2h6a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                    <span class="flex-1 font-medium text-slate-700 truncate">{{ $f['name'] }}</span>
                    @if (!is_null($f['count']))
                        <span class="text-xs text-slate-400">{{ $f['count'] }}</span>
                    @endif
                    <button @click.stop="menu = (menu === '{{ $loop->index }}' ? null : '{{ $loop->index }}')"
                            class="p-1 rounded-md text-slate-400 hover:bg-brand-100 hover:text-slate-600 transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4z"/></svg>
                    </button>
                    {{-- menu kebab (template) --}}
                    <div x-show="menu === '{{ $loop->index }}'" x-cloak @click.outside="menu = null" style="display:none"
                         class="absolute right-3 top-12 z-20 w-40 bg-white border border-brand-100 rounded-xl shadow-lg py-1 text-sm">
                        <button class="w-full text-left px-4 py-2 hover:bg-brand-50 text-slate-600">Buka</button>
                        <button class="w-full text-left px-4 py-2 hover:bg-brand-50 text-slate-600">Ubah nama</button>
                        <button class="w-full text-left px-4 py-2 hover:bg-red-50 text-red-600">Hapus</button>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-xs text-slate-400 mt-6">Template tampilan — data folder script akan dikirim otomatis oleh Hermes agent.</p>
    </div>
</div>
</body>
</html>
