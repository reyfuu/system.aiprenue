<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pipeline FK-AI Preneur</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-brand-50 text-slate-800 min-h-screen" x-data="pipelineApp()">

@include('partials.sidebar')

{{-- Top bar --}}
<header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg md:ml-56">
    <div class="max-w-[1600px] px-6 py-5 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">PIPELINE FK-AI PRENEUR</h1>
            <p class="text-brand-100 text-sm">Manajemen endorsement &amp; pembayaran</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('pipelines.report', ['category' => $category]) }}" target="_blank"
               class="bg-brand-800/40 hover:bg-brand-800/60 text-white text-sm font-semibold px-4 py-2.5 rounded-xl flex items-center gap-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6L19 8.4V18a2 2 0 01-2 2z"/></svg>
                Report PDF
            </a>
            <button @click="openCreate()"
                    class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Entri
            </button>
            <div class="flex items-center gap-2 pl-2 ml-1 border-l border-white/20">
                <span class="text-sm text-brand-100 hidden sm:inline">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="bg-brand-800/40 hover:bg-brand-800/60 text-white text-sm font-semibold px-3 py-2.5 rounded-xl transition flex items-center gap-1.5" title="Keluar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
    {{-- Tabs kategori --}}
    <div class="max-w-[1600px] px-6 flex gap-1">
        @foreach (\App\Models\Pipeline::CATEGORIES as $ck => $cv)
            <a href="{{ route('pipelines.index', ['category' => $ck]) }}"
               class="px-5 py-2.5 text-sm font-semibold rounded-t-xl transition {{ $category === $ck ? 'bg-brand-50 text-brand-700' : 'text-brand-100 hover:bg-brand-800/30' }}">
                {{ $cv }} <span class="ml-1 text-xs opacity-70">({{ $counts[$ck] }})</span>
            </a>
        @endforeach
    </div>
</header>

<div class="max-w-[1600px] px-6 py-6 md:ml-56">

    @if (session('status'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             x-transition.opacity.duration.300ms
             class="fixed top-5 right-5 z-[70] bg-emerald-600 text-white text-sm px-4 py-3 rounded-xl shadow-lg flex items-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>
            <span>{{ session('status') }}</span>
            <button @click="show = false" class="ml-2 text-white/80 hover:text-white text-lg leading-none">&times;</button>
        </div>
    @endif

    {{-- Info kurs terkini --}}
    <div class="flex items-center gap-2 mb-3 text-xs">
        <span class="inline-flex items-center gap-1.5 bg-white border border-brand-100 rounded-full px-3 py-1 shadow-sm">
            <span class="w-2 h-2 rounded-full {{ $summary['rate'] != 16000 ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
            Kurs {{ $summary['rate'] != 16000 ? 'terkini' : 'fallback' }}:
            <strong class="text-brand-700">1 USD = Rp {{ number_format($summary['rate'], 0, ',', '.') }}</strong>
        </span>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
        <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
            <p class="text-xs text-slate-500 font-medium">Omzet IDR</p>
            <p class="text-lg font-bold text-brand-700 mt-1">Rp {{ number_format($summary['total_idr'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
            <p class="text-xs text-slate-500 font-medium">Omzet USD</p>
            <p class="text-lg font-bold text-brand-700 mt-1">$ {{ number_format($summary['total_usd'], 2) }}</p>
        </div>
        <div class="bg-gradient-to-br from-brand-600 to-brand-700 rounded-2xl shadow-sm p-4 text-white">
            <p class="text-xs text-brand-100 font-medium">Total Omzet (IDR)</p>
            <p class="text-lg font-bold mt-1">Rp {{ number_format($summary['grand_idr'], 0, ',', '.') }}</p>
            <p class="text-[10px] text-brand-200 mt-0.5">USD dikonversi otomatis</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
            <p class="text-xs text-slate-500 font-medium">Outstanding (Belum+DP)</p>
            <p class="text-lg font-bold text-red-600 mt-1">{{ $summary['outstanding'] }} entri</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
            <p class="text-xs text-slate-500 font-medium">Lunas</p>
            <p class="text-lg font-bold text-emerald-600 mt-1">{{ $summary['lunas'] }} / {{ $summary['total'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
            <p class="text-xs text-slate-500 font-medium">Progress Done</p>
            <p class="text-lg font-bold text-brand-700 mt-1">{{ $summary['done'] }} / {{ $summary['total'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4 mb-5 flex flex-wrap gap-2 items-center text-sm">
        <input type="hidden" name="category" value="{{ $category }}">
        <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari endorse / notes..."
               x-on:input.debounce.400ms="$el.form.submit()"
               class="border border-slate-200 rounded-xl px-3 py-2 w-56 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none">
        <select name="account" onchange="this.form.submit()" class="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
            <option value="">Semua Account</option>
            @foreach (\App\Models\Pipeline::ACCOUNTS as $k => $v)
                <option value="{{ $k }}" @selected(($filters['account'] ?? '') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="progress" onchange="this.form.submit()" class="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
            <option value="">Semua Progress</option>
            @foreach (\App\Models\Pipeline::PROGRESS as $k => $v)
                <option value="{{ $k }}" @selected(($filters['progress'] ?? '') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="payment_status" onchange="this.form.submit()" class="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
            <option value="">Semua Payment</option>
            @foreach (\App\Models\Pipeline::PAYMENT as $k => $v)
                <option value="{{ $k }}" @selected(($filters['payment_status'] ?? '') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="output" onchange="this.form.submit()" class="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
            <option value="">Semua Output</option>
            @foreach ($outputs as $out)
                <option value="{{ $out->id }}" @selected(($filters['output'] ?? '') == $out->id)>{{ $out->name }}</option>
            @endforeach
        </select>
        <noscript><button class="bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 rounded-xl font-semibold">Filter</button></noscript>
        <a href="{{ route('pipelines.index') }}" class="text-brand-600 hover:text-brand-800 px-2 font-medium">Reset</a>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-brand-100 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-brand-700 text-white text-xs uppercase tracking-wide">
                    <th class="px-4 py-3 text-left">Account</th>
                    <th class="px-4 py-3 text-left">Endorse</th>
                    <th class="px-4 py-3 text-left">Output</th>
                    <th class="px-4 py-3 text-left">Progress</th>
                    <th class="px-4 py-3 text-left">Tgl Posting</th>
                    <th class="px-4 py-3 text-left">Tgl Payment</th>
                    <th class="px-4 py-3 text-left">Payment</th>
                    <th class="px-4 py-3 text-right">IDR</th>
                    <th class="px-4 py-3 text-right">USD</th>
                    <th class="px-4 py-3 text-left">Notes</th>
                    <th class="px-4 py-3 text-left">Ke Gilang</th>
                    <th class="px-4 py-3 text-left">Catatan</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @forelse ($pipelines as $p)
                    <tr class="hover:bg-brand-50/60 transition">
                        <td class="px-4 py-2.5">
                            <span class="inline-block {{ \App\Models\Pipeline::ACCOUNT_COLORS[$p->account] }} text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                {{ \App\Models\Pipeline::ACCOUNTS[$p->account] }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 font-semibold text-slate-700">{{ $p->endorse }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($p->outputs as $out)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-brand-100 text-brand-700 border border-brand-200">{{ $out->name }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            @php $pc = [
                                'script'   => 'bg-purple-600 text-white',
                                'editing'  => 'bg-brand-100 text-brand-700',
                                'progress' => 'bg-brand-600 text-white',
                                'pending'  => 'bg-amber-400 text-amber-900',
                                'done'     => 'bg-emerald-600 text-white',
                            ][$p->progress]; @endphp
                            <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full {{ $pc }}">{{ \App\Models\Pipeline::PROGRESS[$p->progress] }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $p->tanggal_posting?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $p->tanggal_payment?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            @php $yc = ['lunas' => 'bg-emerald-600 text-white', 'dp' => 'bg-amber-400 text-amber-900', 'belum' => 'bg-red-600 text-white'][$p->payment_status]; @endphp
                            <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full {{ $yc }}">{{ \App\Models\Pipeline::PAYMENT[$p->payment_status] }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap font-medium">{{ $p->amount_idr ? 'Rp '.number_format($p->amount_idr, 0, ',', '.') : '—' }}</td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap font-medium">{{ $p->amount_usd ? '$'.number_format($p->amount_usd, 2) : '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500 max-w-[200px]">{{ $p->notes ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            @php $gc = ['done' => 'bg-emerald-600 text-white', 'sudah' => 'bg-brand-100 text-brand-700', 'belum' => 'bg-red-600 text-white'][$p->ke_gilang]; @endphp
                            <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full {{ $gc }}">{{ \App\Models\Pipeline::KE_GILANG[$p->ke_gilang] }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-slate-500 max-w-[160px]">{{ $p->catatan ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-1.5">
                                <button @click='openEdit(@json($p->load("outputs")))'
                                        class="bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15.6 8 16.6l1-3.8 8.6-8.6z"/></svg>
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('pipelines.destroy', $p) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="button"
                                            @click="askConfirm('danger', 'Hapus Entri', 'Yakin ingin menghapus &quot;{{ addslashes($p->endorse) }}&quot;? Tindakan ini tidak bisa dibatalkan.', $el.closest('form'))"
                                            class="bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="px-4 py-10 text-center text-slate-400">Belum ada entri.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-xs text-slate-400 mt-3">{{ $pipelines->count() }} entri ditampilkan.</p>
</div>

{{-- Modal Tambah/Edit --}}
<div x-show="open" x-cloak style="display:none"
     class="fixed inset-0 bg-brand-900/40 backdrop-blur-sm flex items-start justify-center overflow-y-auto py-10 z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 border-t-4 border-brand-600" @click.outside="open=false">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-bold text-brand-800" x-text="mode === 'create' ? '+ Tambah Entri' : 'Edit Entri'"></h2>
            <button type="button" @click="open=false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
        </div>
        <form :action="formAction" method="POST" class="grid grid-cols-2 gap-4 text-sm">
            @csrf
            <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>

            <label class="block font-medium text-slate-600">Kategori
                <select name="category" x-model="form.category" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    @foreach (\App\Models\Pipeline::CATEGORIES as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block font-medium text-slate-600">Account
                <select name="account" x-model="form.account" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    <option value="fk">FK</option>
                    <option value="ai_preneur">AI Preneur</option>
                </select>
            </label>
            <label class="block col-span-2 font-medium text-slate-600">Endorse / Produk
                <input name="endorse" x-model="form.endorse" required class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
            </label>

            <label class="block col-span-2 font-medium text-slate-600">Output
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($outputs as $out)
                        <label class="inline-flex items-center gap-1.5 bg-brand-50 border border-brand-100 rounded-lg px-3 py-1.5 cursor-pointer">
                            <input type="checkbox" name="outputs[]" value="{{ $out->id }}" class="accent-brand-600"
                                   :checked="form.outputs.includes({{ $out->id }})"> {{ $out->name }}
                        </label>
                    @endforeach
                </div>
            </label>

            <label class="block font-medium text-slate-600">Progress
                <select name="progress" x-model="form.progress" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    @foreach (\App\Models\Pipeline::PROGRESS as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block font-medium text-slate-600">Payment Status
                <select name="payment_status" x-model="form.payment_status" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    <option value="belum">Belum</option>
                    <option value="dp">DP</option>
                    <option value="lunas">Lunas</option>
                </select>
            </label>

            <label class="block font-medium text-slate-600">Tanggal Posting
                <input type="date" name="tanggal_posting" x-model="form.tanggal_posting" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
            </label>
            <label class="block font-medium text-slate-600">Tanggal Payment
                <input type="date" name="tanggal_payment" x-model="form.tanggal_payment" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
            </label>

            <label class="block font-medium text-slate-600">Jumlah IDR
                <input type="number" step="0.01" name="amount_idr" x-model="form.amount_idr" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                <span class="text-[11px] text-brand-600" x-show="form.amount_idr > 0" x-text="'≈ $ ' + (form.amount_idr / rate).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})"></span>
            </label>
            <label class="block font-medium text-slate-600">Jumlah USD
                <input type="number" step="0.01" name="amount_usd" x-model="form.amount_usd" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                <span class="text-[11px] text-brand-600" x-show="form.amount_usd > 0" x-text="'≈ Rp ' + Math.round(form.amount_usd * rate).toLocaleString('id-ID')"></span>
            </label>

            <label class="block font-medium text-slate-600">Ke Gilang
                <select name="ke_gilang" x-model="form.ke_gilang" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    @foreach (\App\Models\Pipeline::KE_GILANG as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block font-medium text-slate-600">Catatan
                <input name="catatan" x-model="form.catatan" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
            </label>

            <label class="block col-span-2 font-medium text-slate-600">Notes
                <textarea name="notes" x-model="form.notes" rows="2" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"></textarea>
            </label>

            <div class="col-span-2 flex justify-end gap-2 mt-2">
                <button type="button" @click="open=false" class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                <button type="button"
                        @click="askConfirm('info', mode === 'create' ? 'Tambah Entri' : 'Simpan Perubahan', mode === 'create' ? 'Tambahkan entri baru ini?' : 'Simpan perubahan pada entri ini?', $el.closest('form'))"
                        class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Dialog Konfirmasi (peringatan) --}}
<div x-show="confirmOpen" x-cloak style="display:none"
     class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[60] p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center" @click.outside="confirmOpen=false">
        <div class="mx-auto w-14 h-14 rounded-full flex items-center justify-center mb-4"
             :class="confirmType === 'danger' ? 'bg-red-100' : 'bg-brand-100'">
            <svg x-show="confirmType === 'danger'" class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            <svg x-show="confirmType !== 'danger'" class="w-7 h-7 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h3 class="text-lg font-bold text-slate-800" x-text="confirmTitle"></h3>
        <p class="text-sm text-slate-500 mt-2" x-text="confirmMsg"></p>
        <div class="flex gap-2 mt-6">
            <button type="button" @click="confirmOpen=false"
                    class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-semibold">Batal</button>
            <button type="button" @click="doConfirm()"
                    class="flex-1 px-4 py-2.5 rounded-xl text-white font-semibold transition"
                    :class="confirmType === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-brand-600 hover:bg-brand-700'"
                    x-text="confirmType === 'danger' ? 'Ya, Hapus' : 'Ya, Simpan'"></button>
        </div>
    </div>
</div>

<script>
function pipelineApp() {
    return {
        open: false,
        mode: 'create',
        rate: {{ $summary['rate'] }},
        confirmOpen: false,
        confirmType: 'info',
        confirmTitle: '',
        confirmMsg: '',
        _form: null,
        form: {},
        init() {
            this.form = this.blank();
        },
        askConfirm(type, title, msg, form) {
            this.confirmType = type;
            this.confirmTitle = title;
            this.confirmMsg = msg;
            this._form = form;
            this.confirmOpen = true;
        },
        doConfirm() {
            this.confirmOpen = false;
            const f = this._form;
            if (f) { f.requestSubmit ? f.requestSubmit() : f.submit(); }
        },
        formAction: '{{ route('pipelines.store') }}',
        blank() {
            return { category: '{{ $category }}', account: 'fk', endorse: '', outputs: [], progress: 'script',
                     payment_status: 'belum', tanggal_posting: '', tanggal_payment: '',
                     amount_idr: '', amount_usd: '', notes: '', ke_gilang: 'belum', catatan: '' };
        },
        openCreate() {
            this.mode = 'create';
            this.formAction = '{{ route('pipelines.store') }}';
            this.form = this.blank();
            this.open = true;
        },
        openEdit(p) {
            this.mode = 'edit';
            this.formAction = '/pipelines/' + p.id;
            this.form = {
                category: p.category, account: p.account, endorse: p.endorse,
                outputs: p.outputs.map(o => o.id),
                progress: p.progress, payment_status: p.payment_status,
                tanggal_posting: p.tanggal_posting ? p.tanggal_posting.substring(0,10) : '',
                tanggal_payment: p.tanggal_payment ? p.tanggal_payment.substring(0,10) : '',
                amount_idr: p.amount_idr ?? '', amount_usd: p.amount_usd ?? '',
                notes: p.notes ?? '', ke_gilang: p.ke_gilang, catatan: p.catatan ?? ''
            };
            this.open = true;
        }
    }
}
</script>
</body>
</html>
