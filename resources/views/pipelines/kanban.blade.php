@php
    use App\Models\Pipeline;
    // dot + subtitle per kolom
    $columnMeta = [
        'script'   => ['dot' => 'bg-purple-500',  'desc' => 'Naskah sedang disiapkan'],
        'editing'  => ['dot' => 'bg-sky-500',      'desc' => 'Proses editing konten'],
        'progress' => ['dot' => 'bg-brand-600',    'desc' => 'Sedang dikerjakan — in-flight'],
        'pending'  => ['dot' => 'bg-amber-500',    'desc' => 'Tertunda menunggu sesuatu'],
        'done'     => ['dot' => 'bg-emerald-500',  'desc' => 'Selesai & terpublikasi'],
    ];
    $board = [];
    foreach (array_keys(Pipeline::PROGRESS) as $k) {
        $board[$k] = [];
    }
    foreach ($pipelines as $p) {
        $board[$p->progress][] = [
            'id'            => $p->id,
            'code'          => 't_'.str_pad($p->id, 6, '0', STR_PAD_LEFT),
            'endorse'       => $p->endorse,
            'account'       => Pipeline::ACCOUNTS[$p->account],
            'account_color' => Pipeline::ACCOUNT_COLORS[$p->account],
            'outputs'       => $p->outputs->pluck('name'),
            'payment'       => Pipeline::PAYMENT[$p->payment_status],
            'payment_status'=> $p->payment_status,
            'amount_idr'    => $p->amount_idr,
            'amount_usd'    => $p->amount_usd,
            'assignee'      => $p->assignee?->name,
            'link'          => $p->link,
            'time'          => $p->updated_at?->diffForHumans(null, true).' lalu',
        ];
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kanban — Pipeline FK-AI Preneur</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none}</style>
</head>
<body class="bg-brand-50 text-slate-800 min-h-screen" x-data="kanbanBoard()">

@include('partials.sidebar')

<div class="md:ml-56 p-6">

    {{-- Toolbar atas: board selector + task count --}}
    <div class="bg-white border border-brand-100 rounded-2xl shadow-sm p-4 mb-3 flex items-center gap-3">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-slate-400 mb-1">Board</p>
            <select onchange="location.href='{{ route('pipelines.kanban') }}?category='+this.value"
                    class="bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:ring-2 focus:ring-brand-400 outline-none">
                @foreach (Pipeline::CATEGORIES as $ck => $cv)
                    <option value="{{ $ck }}" @selected($category === $ck)>{{ $cv }} · {{ $counts[$ck] }}</option>
                @endforeach
            </select>
        </div>
        <span class="text-sm text-slate-400 mt-5">{{ $counts[$category] }} task</span>
    </div>

    {{-- Search / filter --}}
    <div class="flex items-center gap-3 mb-5">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-slate-400 mb-1">Search</p>
            <input x-model="q" placeholder="Filter cards…"
                   class="bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm w-64 text-slate-700 placeholder-slate-400 focus:ring-2 focus:ring-brand-400 outline-none">
        </div>
        <div class="flex items-center gap-2 mt-5 ml-auto">
            <button @click="q=''" class="bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 text-sm px-4 py-2 rounded-lg transition">Clear filters</button>
            <button onclick="location.reload()" class="bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 text-sm px-4 py-2 rounded-lg transition">Refresh</button>
        </div>
    </div>

    {{-- Kolom --}}
    <div class="overflow-x-auto pb-4">
        {{-- Kolom adaptif: memenuhi lebar (5 kolom pas di ~1080p), scroll halus hanya bila layar sangat sempit --}}
        <div class="flex gap-3 min-w-[1040px]">
            @foreach (Pipeline::PROGRESS as $key => $label)
                <div class="flex-1 basis-0 min-w-[196px] bg-white border border-brand-100 rounded-2xl shadow-sm p-3"
                     @dragover.prevent
                     @drop="onDrop('{{ $key }}')">
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ $columnMeta[$key]['dot'] }}"></span>
                            <h2 class="text-sm font-bold text-slate-700">{{ $label }}</h2>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-slate-400" x-text="filtered('{{ $key }}').length"></span>
                            <button @click="openAdd('{{ $key }}')" title="Tambah task"
                                    class="w-6 h-6 flex items-center justify-center rounded-md bg-brand-50 hover:bg-brand-100 text-brand-600 font-bold leading-none transition">+</button>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400 mb-3 leading-snug min-h-[28px]">{{ $columnMeta[$key]['desc'] }}</p>

                    <div class="space-y-2.5 min-h-[120px] rounded-xl p-2 bg-brand-50/60">
                        <template x-for="card in filtered('{{ $key }}')" :key="card.id">
                            <div draggable="true"
                                 @dragstart="onDragStart(card.id, '{{ $key }}')"
                                 class="bg-white border border-brand-100 rounded-xl p-3 shadow-sm cursor-grab active:cursor-grabbing hover:shadow-md hover:border-brand-200 transition">
                                <p class="text-[10px] text-slate-400 font-mono mb-1" x-text="card.code"></p>
                                <p class="font-semibold text-sm text-slate-700 leading-snug mb-2" x-text="card.endorse"></p>
                                <div class="flex flex-wrap gap-1 mb-2" x-show="card.outputs.length">
                                    <template x-for="o in card.outputs" :key="o">
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-brand-100 text-brand-700 border border-brand-200" x-text="o"></span>
                                    </template>
                                </div>
                                <div class="flex items-center justify-between text-[10px] mb-1.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-semibold px-2 py-0.5 rounded-full" :class="card.account_color" x-text="card.account"></span>
                                        <span class="font-semibold px-2 py-0.5 rounded-full"
                                              :class="{'bg-emerald-600 text-white': card.payment_status==='lunas','bg-amber-400 text-amber-900': card.payment_status==='dp','bg-red-600 text-white': card.payment_status==='belum'}"
                                              x-text="card.payment"></span>
                                    </div>
                                    <span class="text-slate-400" x-text="card.time"></span>
                                </div>
                                <div class="flex items-center justify-between gap-2 text-[10px] pt-1.5 border-t border-brand-50">
                                    <span class="flex items-center gap-1 text-slate-500 truncate" x-show="card.assignee">
                                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        <span class="truncate font-medium" x-text="card.assignee"></span>
                                    </span>
                                    <span class="text-slate-300 italic" x-show="!card.assignee">belum ditugaskan</span>
                                    <a :href="card.link" target="_blank" x-show="card.link" @dragstart.stop @click.stop
                                       class="flex items-center gap-0.5 text-brand-600 hover:text-brand-800 font-medium flex-shrink-0">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                        Link
                                    </a>
                                </div>
                            </div>
                        </template>
                        <p class="text-center text-xs text-slate-400 py-6" x-show="!filtered('{{ $key }}').length">— no tasks —</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Modal tambah task --}}
<div x-show="addOpen" x-cloak style="display:none"
     class="fixed inset-0 bg-brand-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 border-t-4 border-brand-600" @click.outside="addOpen=false">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-brand-800">Tambah Task <span class="text-sm font-normal text-slate-400" x-text="'· ' + addLabel"></span></h2>
            <button type="button" @click="addOpen=false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
        </div>
        <form method="POST" action="{{ route('pipelines.store') }}" class="space-y-3 text-sm">
            @csrf
            <input type="hidden" name="category" value="{{ $category }}">
            <input type="hidden" name="progress" :value="addProgress">
            <input type="hidden" name="payment_status" value="belum">
            <input type="hidden" name="ke_gilang" value="belum">
            <label class="block font-medium text-slate-600">Judul / Endorse
                <input name="endorse" x-model="addTitle" required autofocus
                       class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
            </label>
            <label class="block font-medium text-slate-600">Account
                <select name="account" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    <option value="fk">FK</option>
                    <option value="ai_preneur">AI Preneur</option>
                    <option value="raveloux">Raveloux</option>
                    <option value="rave tailor">rave tailor</option>
                </select>
            </label>
            <label class="block font-medium text-slate-600">Penanggung Jawab (Staff)
                <select name="assigned_to" x-model="addAssignee" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    <option value="">— belum ditugaskan —</option>
                    @foreach ($staff as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ \App\Models\User::ROLES[$s->role] ?? $s->role }})</option>
                    @endforeach
                </select>
            </label>
            <label class="block font-medium text-slate-600">Link Video (opsional)
                <input type="url" name="link" x-model="addLink" placeholder="https://…"
                       class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
            </label>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="addOpen=false" class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function kanbanBoard() {
    const labels = @json(Pipeline::PROGRESS);
    return {
        columns: @json($board),
        q: '',
        dragId: null,
        dragFrom: null,
        addOpen: false,
        addProgress: 'script',
        addTitle: '',
        addAssignee: '',
        addLink: '',
        get addLabel() { return labels[this.addProgress] ?? ''; },
        openAdd(progress) {
            this.addProgress = progress;
            this.addTitle = '';
            this.addAssignee = '';
            this.addLink = '';
            this.addOpen = true;
        },
        filtered(col) {
            const s = this.q.trim().toLowerCase();
            if (!s) return this.columns[col];
            return this.columns[col].filter(c =>
                c.endorse.toLowerCase().includes(s) || c.code.toLowerCase().includes(s));
        },
        onDragStart(id, from) { this.dragId = id; this.dragFrom = from; },
        onDrop(to) {
            if (this.dragId === null || this.dragFrom === to) return;
            const card = this.columns[this.dragFrom].find(c => c.id === this.dragId);
            if (!card) return;
            this.columns[this.dragFrom] = this.columns[this.dragFrom].filter(c => c.id !== this.dragId);
            this.columns[to].push(card);
            const id = this.dragId;
            this.dragId = null;
            fetch(`/pipelines/${id}/progress`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({ progress: to }),
            }).catch(() => location.reload()); // ponytail: resync by reload if save fails
        },
    }
}
</script>
</body>
</html>
