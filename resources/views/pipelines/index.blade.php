<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pipeline FK-AI Preneur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 text-gray-800"
      x-data="pipelineApp()">

<div class="max-w-[1400px] mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold tracking-tight">PIPELINE FK-AI PRENEUR</h1>
        <button @click="openCreate()"
                class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg shadow">
            + Tambah Entri
        </button>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-2 rounded-lg">
            {{ session('status') }}
        </div>
    @endif

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
        <div class="bg-white rounded-xl shadow-sm p-4">
            <p class="text-xs text-gray-500">Total Revenue IDR</p>
            <p class="text-lg font-bold">Rp {{ number_format($summary['total_idr'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <p class="text-xs text-gray-500">Total Revenue USD</p>
            <p class="text-lg font-bold">$ {{ number_format($summary['total_usd'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <p class="text-xs text-gray-500">Outstanding (Belum+DP)</p>
            <p class="text-lg font-bold text-red-600">{{ $summary['outstanding'] }} entri</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <p class="text-xs text-gray-500">Lunas</p>
            <p class="text-lg font-bold text-emerald-600">{{ $summary['lunas'] }} / {{ $summary['total'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <p class="text-xs text-gray-500">Progress Done</p>
            <p class="text-lg font-bold">{{ $summary['done'] }} / {{ $summary['total'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl shadow-sm p-3 mb-4 flex flex-wrap gap-2 items-center text-sm">
        <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari endorse / notes..."
               class="border rounded-lg px-3 py-1.5 w-52">
        <select name="account" class="border rounded-lg px-2 py-1.5">
            <option value="">Semua Account</option>
            @foreach (\App\Models\Pipeline::ACCOUNTS as $k => $v)
                <option value="{{ $k }}" @selected(($filters['account'] ?? '') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="progress" class="border rounded-lg px-2 py-1.5">
            <option value="">Semua Progress</option>
            @foreach (\App\Models\Pipeline::PROGRESS as $k => $v)
                <option value="{{ $k }}" @selected(($filters['progress'] ?? '') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="payment_status" class="border rounded-lg px-2 py-1.5">
            <option value="">Semua Payment</option>
            @foreach (\App\Models\Pipeline::PAYMENT as $k => $v)
                <option value="{{ $k }}" @selected(($filters['payment_status'] ?? '') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="output" class="border rounded-lg px-2 py-1.5">
            <option value="">Semua Output</option>
            @foreach ($outputs as $out)
                <option value="{{ $out->id }}" @selected(($filters['output'] ?? '') == $out->id)>{{ $out->name }}</option>
            @endforeach
        </select>
        <button class="bg-gray-800 text-white px-4 py-1.5 rounded-lg">Filter</button>
        <a href="{{ route('pipelines.index') }}" class="text-gray-500 px-2">Reset</a>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-gray-800 text-white text-xs uppercase tracking-wide">
                    <th class="px-3 py-2 text-left">Account</th>
                    <th class="px-3 py-2 text-left">Endorse</th>
                    <th class="px-3 py-2 text-left">Output</th>
                    <th class="px-3 py-2 text-left">Progress</th>
                    <th class="px-3 py-2 text-left">Tgl Posting</th>
                    <th class="px-3 py-2 text-left">Tgl Payment</th>
                    <th class="px-3 py-2 text-left">Payment</th>
                    <th class="px-3 py-2 text-right">IDR</th>
                    <th class="px-3 py-2 text-right">USD</th>
                    <th class="px-3 py-2 text-left">Notes</th>
                    <th class="px-3 py-2 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($pipelines as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2">
                            <span class="inline-block bg-blue-600 text-white text-xs font-semibold px-2 py-0.5 rounded">
                                {{ \App\Models\Pipeline::ACCOUNTS[$p->account] }}
                            </span>
                        </td>
                        <td class="px-3 py-2 font-medium">{{ $p->endorse }}</td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($p->outputs as $out)
                                    <span class="text-xs px-2 py-0.5 rounded bg-{{ $out->color }}-100 text-{{ $out->color }}-800 border border-{{ $out->color }}-200">{{ $out->name }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            @php $pc = ['done' => 'bg-emerald-600 text-white', 'progress' => 'bg-purple-600 text-white', 'editing' => 'bg-emerald-200 text-emerald-900'][$p->progress]; @endphp
                            <span class="text-xs font-semibold px-2 py-0.5 rounded {{ $pc }}">{{ \App\Models\Pipeline::PROGRESS[$p->progress] }}</span>
                        </td>
                        <td class="px-3 py-2 text-gray-600">{{ $p->tanggal_posting?->format('d M Y') ?? '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $p->tanggal_payment?->format('d M Y') ?? '—' }}</td>
                        <td class="px-3 py-2">
                            @php $yc = ['lunas' => 'bg-emerald-600 text-white', 'dp' => 'bg-amber-400 text-amber-900', 'belum' => 'bg-red-600 text-white'][$p->payment_status]; @endphp
                            <span class="text-xs font-semibold px-2 py-0.5 rounded {{ $yc }}">{{ \App\Models\Pipeline::PAYMENT[$p->payment_status] }}</span>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">{{ $p->amount_idr ? 'Rp '.number_format($p->amount_idr, 0, ',', '.') : '—' }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">{{ $p->amount_usd ? '$'.number_format($p->amount_usd, 2) : '—' }}</td>
                        <td class="px-3 py-2 text-gray-600 max-w-[200px]">{{ $p->notes ?? '—' }}</td>
                        <td class="px-3 py-2 text-center whitespace-nowrap">
                            <button @click='openEdit(@json($p->load("outputs")))' class="text-blue-600 hover:underline text-xs">Edit</button>
                            <form method="POST" action="{{ route('pipelines.destroy', $p) }}" class="inline"
                                  onsubmit="return confirm('Hapus entri ini?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline text-xs ml-1">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="px-3 py-8 text-center text-gray-400">Belum ada entri.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400 mt-2">{{ $pipelines->count() }} entri ditampilkan.</p>
</div>

{{-- Modal Tambah/Edit --}}
<div x-show="open" x-cloak style="display:none"
     class="fixed inset-0 bg-black/40 flex items-start justify-center overflow-y-auto py-10 z-50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6" @click.outside="open=false">
        <h2 class="text-lg font-bold mb-4" x-text="mode === 'create' ? 'Tambah Entri' : 'Edit Entri'"></h2>
        <form :action="formAction" method="POST" class="grid grid-cols-2 gap-4 text-sm">
            @csrf
            <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>

            <label class="block">Account
                <select name="account" x-model="form.account" class="mt-1 w-full border rounded-lg px-2 py-2">
                    <option value="fk">FK</option>
                    <option value="ai_preneur">AI Preneur</option>
                </select>
            </label>
            <label class="block">Endorse (produk)
                <input name="endorse" x-model="form.endorse" required class="mt-1 w-full border rounded-lg px-2 py-2">
            </label>

            <label class="block col-span-2">Output
                <div class="mt-1 flex flex-wrap gap-3">
                    @foreach ($outputs as $out)
                        <label class="inline-flex items-center gap-1">
                            <input type="checkbox" name="outputs[]" value="{{ $out->id }}"
                                   :checked="form.outputs.includes({{ $out->id }})"> {{ $out->name }}
                        </label>
                    @endforeach
                </div>
            </label>

            <label class="block">Progress
                <select name="progress" x-model="form.progress" class="mt-1 w-full border rounded-lg px-2 py-2">
                    <option value="editing">Editing</option>
                    <option value="progress">Progress</option>
                    <option value="done">Done</option>
                </select>
            </label>
            <label class="block">Payment Status
                <select name="payment_status" x-model="form.payment_status" class="mt-1 w-full border rounded-lg px-2 py-2">
                    <option value="belum">Belum</option>
                    <option value="dp">DP</option>
                    <option value="lunas">Lunas</option>
                </select>
            </label>

            <label class="block">Tanggal Posting
                <input type="date" name="tanggal_posting" x-model="form.tanggal_posting" class="mt-1 w-full border rounded-lg px-2 py-2">
            </label>
            <label class="block">Tanggal Payment
                <input type="date" name="tanggal_payment" x-model="form.tanggal_payment" class="mt-1 w-full border rounded-lg px-2 py-2">
            </label>

            <label class="block">Jumlah IDR
                <input type="number" step="0.01" name="amount_idr" x-model="form.amount_idr" class="mt-1 w-full border rounded-lg px-2 py-2">
            </label>
            <label class="block">Jumlah USD
                <input type="number" step="0.01" name="amount_usd" x-model="form.amount_usd" class="mt-1 w-full border rounded-lg px-2 py-2">
            </label>

            <label class="block col-span-2">Notes
                <textarea name="notes" x-model="form.notes" rows="2" class="mt-1 w-full border rounded-lg px-2 py-2"></textarea>
            </label>

            <div class="col-span-2 flex justify-end gap-2 mt-2">
                <button type="button" @click="open=false" class="px-4 py-2 rounded-lg border">Batal</button>
                <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function pipelineApp() {
    return {
        open: false,
        mode: 'create',
        formAction: '{{ route('pipelines.store') }}',
        form: this.blank(),
        blank() {
            return { account: 'fk', endorse: '', outputs: [], progress: 'progress',
                     payment_status: 'belum', tanggal_posting: '', tanggal_payment: '',
                     amount_idr: '', amount_usd: '', notes: '' };
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
                account: p.account, endorse: p.endorse,
                outputs: p.outputs.map(o => o.id),
                progress: p.progress, payment_status: p.payment_status,
                tanggal_posting: p.tanggal_posting ? p.tanggal_posting.substring(0,10) : '',
                tanggal_payment: p.tanggal_payment ? p.tanggal_payment.substring(0,10) : '',
                amount_idr: p.amount_idr ?? '', amount_usd: p.amount_usd ?? '',
                notes: p.notes ?? ''
            };
            this.open = true;
        }
    }
}
</script>
<style>[x-cloak]{display:none!important}</style>
</body>
</html>
