<?php

namespace App\Http\Controllers;

use App\Models\Output;
use App\Models\Pipeline;
use App\Models\User;
use App\Support\ExchangeRate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PipelineController extends Controller
{
    /** Sales pipeline — board tipe `pipeline`, dirender pakai UI kanban yang sama.
     *  Tanpa galeri: board dipilih lewat dropdown toolbar. */
    public function index(Request $request)
    {
        $categories = Pipeline::categories('pipeline');
        $keys = array_keys($categories);

        // ?category tak valid → board sales (menu ini = Sales Pipeline), fallback board pertama.
        // ponytail: key 'sales' di-hardcode; kalau nanti perlu board default yg bisa diatur,
        // tambah flag `is_default` di tabel categories.
        $default = in_array('sales', $keys, true) ? 'sales' : ($keys[0] ?? null);
        $category = in_array($request->category, $keys, true) ? $request->category : $default;
        if ($category === null) {
            abort(404, 'Belum ada board pipeline.');
        }

        return $this->renderBoard($request, $category, $categories, '/pipelines', 'Sales Pipeline', false);
    }

    /** Kanban LUAR: galeri semua board dikelompokkan per section. */
    private function gallery()
    {
        $boards = \App\Models\Category::where('type', 'kanban')->orderBy('name')->get();
        $counts = Pipeline::whereNull('archived_at')->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')->pluck('total', 'category')->toArray();

        return Inertia::render('BoardGallery', [
            'boards' => $boards->map(fn ($b) => [
                'key'              => $b->key,
                'name'             => $b->name,
                'section'          => $b->section ?: 'Tanpa Grup',      // grup galeri
                'super_admin_only' => (bool) $b->super_admin_only,
                'count'            => $counts[$b->key] ?? 0,             // jml task aktif
            ]),
            'canManage' => auth()->user()->canManage(),
        ]);
    }

    public function kanban(Request $request)
    {
        // Tanpa ?category → tampilkan galeri board (kanban luar)
        if (! $request->filled('category')) {
            return $this->gallery();
        }
        $categories = Pipeline::categories('kanban');                // hanya board tipe kanban
        // ?category tak valid → balik ke galeri
        if (! array_key_exists($request->category, $categories)) {
            return redirect()->route('pipelines.kanban');
        }

        return $this->renderBoard($request, $request->category, $categories, '/pipelines/kanban', 'Kanban', true);
    }

    /** Susun & render satu board (Kanban.vue) — dipakai Sales Pipeline & Kanban.
     *  $categories = board yg boleh dipilih di dropdown (sudah difilter per type),
     *  $baseUrl    = path modul (dipakai switch board & toggle arsip),
     *  $showGallery= tampilkan link balik ke galeri (kanban saja). */
    private function renderBoard(Request $request, string $category, array $categories, string $baseUrl, string $title, bool $showGallery)
    {
        // Tampilkan kartu aktif; bila ?archived=1 → tampilkan yg diarsipkan
        $showArchived = $request->boolean('archived');

        // Filter jenis — bisa banyak sekaligus (?jenis[]=endorse&jenis[]=speaker).
        // Nilai ngawur dibuang lewat intersect, BUKAN divalidasi: ?jenis ngawur jangan
        // bikin halaman error, cukup diabaikan.
        // Difilter di QUERY, bukan di Vue: SortableJS memutasi array kolom langsung saat
        // drag, jadi array hasil filter di frontend akan merusak drag & drop.
        // NB: bentuk UI-nya WAJIB chip, bukan dropdown. Versi dropdown pernah ada dan
        // dibuang — letaknya sama dgn dropdown board lama, jadi terbaca "pindah board".
        $jenis = array_values(array_intersect(
            array_map('strval', (array) $request->input('jenis', [])),
            array_keys(Pipeline::JENIS)
        ));

        $pipelines = Pipeline::where('category', $category)
            ->with(['outputs', 'assignee', 'comments.user', 'attachments.user'])
            ->when($showArchived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when($jenis, fn ($q) => $q->whereIn('jenis', $jenis))
            ->orderBy('id')->get();

        // Jumlah kartu per jenis untuk angka di chip — TIDAK ikut $jenis, kalau ikut
        // angkanya jadi 0 begitu chip lain dipilih & tak bisa dipakai memilih.
        // Ikut $showArchived supaya cocok dgn apa yang sedang ditampilkan.
        $jenisCounts = Pipeline::where('category', $category)
            ->when($showArchived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->whereNotNull('jenis')
            ->selectRaw('jenis, COUNT(*) as total')->groupBy('jenis')->pluck('total', 'jenis')->toArray();

        // Estimasi nilai SELURUH board. Sengaja dari query terpisah yang tak ikut
        // $jenis: menjumlah kartu yang tampil (spt boardValue di Vue) bikin angkanya
        // menyusut saat chip dipilih — itu total tersaring, bukan total board.
        $rate = ExchangeRate::usdToIdr();
        $nilai = Pipeline::where('category', $category)
            ->when($showArchived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->selectRaw('COALESCE(SUM(amount_idr),0) as idr, COALESCE(SUM(amount_usd),0) as usd')->first();
        $boardTotal = (float) $nilai->idr + (float) $nilai->usd * $rate;

        // Hitung kartu AKTIF per kategori (arsip tidak dihitung)
        $counts = Pipeline::whereNull('archived_at')->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')->pluck('total', 'category')->toArray();
        $counts = array_merge(array_fill_keys(array_keys($categories), 0), $counts);

        // Jumlah kartu di arsip board ini (untuk tombol toggle)
        $archivedCount = Pipeline::where('category', $category)->whereNotNull('archived_at')->count();

        // Kolom board ini + susun kartu per kolom (derivasi pindah dari blade @php)
        $columns = \App\Models\BoardColumn::forBoard($category);
        $colKeys = $columns->pluck('key')->all();
        $board = array_fill_keys($colKeys, []); // kolom kosong per key
        foreach ($pipelines as $p) {
            // kartu dgn kolom terhapus → jatuh ke kolom pertama
            $ck = in_array($p->progress, $colKeys, true) ? $p->progress : ($colKeys[0] ?? $p->progress);
            $board[$ck][] = [
                'id'             => $p->id,
                'code'           => 't_'.str_pad($p->id, 6, '0', STR_PAD_LEFT),
                'endorse'        => $p->endorse,
                'jenis'          => $p->jenis,                                               // key mentah (form edit)
                'jenis_label'    => $p->jenis ? (Pipeline::JENIS[$p->jenis] ?? $p->jenis) : null,
                'account'        => Pipeline::ACCOUNTS[$p->account] ?? $p->account,          // label akun
                'account_color'  => Pipeline::ACCOUNT_COLORS[$p->account] ?? 'bg-slate-500 text-white',
                'outputs'        => $p->outputs->pluck('name'),
                'payment'        => Pipeline::PAYMENT[$p->payment_status] ?? $p->payment_status,
                'payment_status' => $p->payment_status,
                'amount_idr'     => $p->amount_idr,
                'amount_usd'     => $p->amount_usd,
                'assignee'       => $p->assignee?->name,
                'link'           => $p->link,
                'labels'         => $p->labels ?? [],
                'done'           => (bool) $p->done,                         // kartu ditandai selesai (ala Trello)
                // fitur kartu: deadline, deskripsi, arsip
                'deadline'       => $p->deadline?->toDateString(),
                'description'    => $p->description,
                'archived'       => (bool) $p->archived_at,
                // komentar (terbaru dulu) + lampiran
                'comments'       => $p->comments->sortByDesc('created_at')->values()->map(fn ($c) => [
                    'id'      => $c->id,
                    'body'    => $c->body,
                    'user'    => $c->user?->name,
                    'user_id' => $c->user_id,
                    'time'    => $c->created_at?->diffForHumans(),
                ]),
                'comment_count'    => $p->comments->count(),
                'attachments'      => $p->attachments->map(fn ($a) => [
                    'id'   => $a->id,
                    'name' => $a->name,
                    'url'  => $a->url,
                    'size' => $a->size,
                    'user' => $a->user?->name,
                ]),
                'attachment_count' => $p->attachments->count(),
                // field mentah utk form edit
                'account_key'    => $p->account,
                'assigned_to'    => $p->assigned_to,
                'progress'       => $p->progress,
                'output_ids'     => $p->outputs->pluck('id'),
                'notes'          => $p->notes,
            ];
        }

        $currentBoard = \App\Models\Category::where('key', $category)->first();

        return Inertia::render('Kanban', [
            'category'      => $category,
            'counts'        => $counts,
            'categories'    => $categories,                                  // board select: sesuai type modul
            'baseUrl'       => $baseUrl,                                     // '/pipelines' | '/pipelines/kanban'
            'pageTitle'     => $title,
            'showGallery'   => $showGallery,                                 // link galeri: kanban saja
            // Board baru dari halaman ini harus bertipe sama, kalau tidak langsung hilang dari modul ini
            'boardType'     => $currentBoard?->type ?? 'kanban',
            // Kurs USD→IDR: nilai deal per stage dijumlahkan dalam IDR (kartu bisa USD).
            'rate'          => $rate,
            'boardTotal'    => $boardTotal,                                 // estimasi nilai SELURUH board (tak ikut filter)
            'board'         => $board,                                       // kartu tersusun per kolom
            'columns'       => $columns,                                     // kolom dinamis board ini
            'jenis'         => $jenis,                                      // chip aktif (array; kosong = semua)
            'jenisCounts'   => $jenisCounts,                                // angka di tiap chip
            'showArchived'  => $showArchived,                               // sedang lihat arsip?
            'archivedCount' => $archivedCount,                             // jumlah kartu diarsip
            'staff'         => User::orderBy('name')->get(['id', 'name', 'role']),
            'outputs'      => Output::orderBy('name')->get(),
            'canManage'    => auth()->user()->canManage(),                   // super_admin/it → boleh CRUD
            'currentBoard' => $currentBoard,
            // Referensi untuk form tambah/edit kartu
            'accounts'     => Pipeline::ACCOUNTS,
            'jenisList'    => Pipeline::JENIS,          // endorse/coaching/agensi/speaker
            'payments'     => Pipeline::PAYMENT,
        ]);
    }

    public function updateProgress(Request $request, Pipeline $pipeline)
    {
        $validKeys = \App\Models\BoardColumn::where('board_key', $pipeline->category)->pluck('key')->all();
        $data = $request->validate([
            'progress' => ['required', \Illuminate\Validation\Rule::in($validKeys)],
        ]);
        $pipeline->update($data);

        return response()->json(['ok' => true]);
    }

    /** Tandai kartu selesai / batal (flag `done`, tak memindah kolom). */
    public function updateDone(Request $request, Pipeline $pipeline)
    {
        $data = $request->validate(['done' => 'required|boolean']);
        $pipeline->update($data);

        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $pipeline = Pipeline::create($data);
        $pipeline->outputs()->sync($request->input('outputs', []));

        return redirect()->back()->with('status', 'Entri ditambahkan.');
    }

    public function update(Request $request, Pipeline $pipeline)
    {
        $data = $this->validated($request);
        $pipeline->update($data);
        $pipeline->outputs()->sync($request->input('outputs', []));

        return redirect()->back()->with('status', 'Entri diperbarui.');
    }

    public function destroy(Pipeline $pipeline)
    {
        $pipeline->delete();

        return redirect()->back()->with('status', 'Entri dihapus.');
    }

    /** Arsipkan / kembalikan kartu (toggle archived_at). */
    public function archive(Pipeline $pipeline)
    {
        $archiving = is_null($pipeline->archived_at);                 // sedang mengarsip?
        $pipeline->update(['archived_at' => $archiving ? now() : null]);

        return redirect()->back()->with('status', $archiving ? 'Kartu diarsipkan.' : 'Kartu dikembalikan.');
    }

    private function validated(Request $request): array
    {
        $validProgress = \App\Models\BoardColumn::where('board_key', $request->category)->pluck('key')->all();

        $data = $request->validate([
            'category'        => ['required', \Illuminate\Validation\Rule::in(array_keys(Pipeline::categories()))],
            'jenis'           => ['nullable', \Illuminate\Validation\Rule::in(array_keys(Pipeline::JENIS))],
            'account'         => ['required', \Illuminate\Validation\Rule::in(array_keys(Pipeline::ACCOUNTS))],
            'assigned_to'     => 'nullable|exists:users,id',
            'link'            => 'nullable|url|max:2048',
            'labels'          => 'nullable|array',
            'labels.*.name'   => 'required_with:labels|string|max:50',
            'labels.*.color'  => 'required_with:labels|string|max:40',
            'coaching'        => 'nullable|string|max:255',
            'speaker'         => 'nullable|string|max:255',
            'endorse'         => 'required|string|max:255',
            'description'     => 'nullable|string',
            'progress'        => ['required', \Illuminate\Validation\Rule::in($validProgress ?: ['script'])],
            'tanggal_posting' => 'nullable|date',
            'tanggal_payment' => 'nullable|date',
            'deadline'        => 'nullable|date',
            'payment_status'  => 'required|in:belum,dp,lunas',
            'amount_idr'      => 'nullable|numeric|min:0',
            'amount_usd'      => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
            'outputs'         => 'array',
            'outputs.*'       => 'exists:outputs,id',
        ]);

        return $data;
    }
}
