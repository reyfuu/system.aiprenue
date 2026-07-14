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
    public function index(Request $request)
    {
        $categories = array_keys(Pipeline::categories());
        $category = in_array($request->category, $categories) ? $request->category : ($categories[0] ?? 'endorse');

        $query = Pipeline::query()->where('category', $category)->with('outputs');

        if ($request->filled('account')) {
            $query->where('account', $request->account);
        }
        if ($request->filled('progress')) {
            $query->where('progress', $request->progress);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('output')) {
            $query->whereHas('outputs', fn ($q) => $q->where('outputs.id', $request->output));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('endorse', 'like', "%$s%")
                ->orWhere('notes', 'like', "%$s%"));
        }

        $pipelines = $query->orderBy('id')->get();

        $rate = ExchangeRate::usdToIdr();
        $base = Pipeline::where('category', $category);
        $totalIdr = (clone $base)->sum('amount_idr');
        $totalUsd = (clone $base)->sum('amount_usd');
        $summary = [
            'total_idr'   => $totalIdr,
            'total_usd'   => $totalUsd,
            'grand_idr'   => $totalIdr + ($totalUsd * $rate), // omzet gabungan (USD dikonversi ke IDR)
            'rate'        => $rate,
            'lunas'       => (clone $base)->where('payment_status', 'lunas')->count(),
            'outstanding' => (clone $base)->whereIn('payment_status', ['belum', 'dp'])->count(),
            'done'        => (clone $base)->where('progress', 'done')->count(),
            'total'       => (clone $base)->count(),
        ];

        $counts = Pipeline::selectRaw('category, COUNT(*) as total')
            ->groupBy('category')->pluck('total', 'category')->toArray();
        $counts = array_merge(array_fill_keys($categories, 0), $counts);

        return Inertia::render('Pipelines/Index', [
            'pipelines'  => $pipelines->load('outputs'),
            'category'   => $category,
            'counts'     => $counts,
            'categories' => Pipeline::categories(),                 // key => nama board
            'outputs'    => Output::orderBy('name')->get(),
            'summary'    => $summary,
            'filters'    => $request->only(['account', 'progress', 'payment_status', 'output', 'search']),
            // Referensi untuk filter & form tambah/edit
            'accounts'   => Pipeline::ACCOUNTS,
            'progresses' => Pipeline::PROGRESS,
            'payments'   => Pipeline::PAYMENT,
            'keGilang'   => Pipeline::KE_GILANG,
            'staff'      => User::orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function kanban(Request $request)
    {
        $categories = array_keys(Pipeline::categories());
        $category = in_array($request->category, $categories) ? $request->category : ($categories[0] ?? 'endorse');

        // Tampilkan kartu aktif; bila ?archived=1 → tampilkan yg diarsipkan
        $showArchived = $request->boolean('archived');
        $pipelines = Pipeline::where('category', $category)
            ->with(['outputs', 'assignee', 'comments.user', 'attachments.user'])
            ->when($showArchived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->orderBy('id')->get();

        // Hitung kartu AKTIF per kategori (arsip tidak dihitung)
        $counts = Pipeline::whereNull('archived_at')->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')->pluck('total', 'category')->toArray();
        $counts = array_merge(array_fill_keys($categories, 0), $counts);

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
                'time'           => $p->updated_at?->diffForHumans(null, true).' lalu',
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
                'ke_gilang'      => $p->ke_gilang,
            ];
        }

        return Inertia::render('Kanban', [
            'category'      => $category,
            'counts'        => $counts,
            'categories'    => Pipeline::categories(),                       // key => nama board
            'board'         => $board,                                       // kartu tersusun per kolom
            'columns'       => $columns,                                     // kolom dinamis board ini
            'showArchived'  => $showArchived,                               // sedang lihat arsip?
            'archivedCount' => $archivedCount,                             // jumlah kartu diarsip
            'staff'         => User::orderBy('name')->get(['id', 'name', 'role']),
            'outputs'      => Output::orderBy('name')->get(),
            'canManage'    => auth()->user()->canManage(),                   // super_admin/it → boleh CRUD
            'currentBoard' => \App\Models\Category::where('key', $category)->first(),
            // Referensi untuk form tambah/edit kartu
            'accounts'     => Pipeline::ACCOUNTS,
            'payments'     => Pipeline::PAYMENT,
            'keGilang'     => Pipeline::KE_GILANG,
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

    public function report(Request $request)
    {
        $category = in_array($request->category, array_keys(Pipeline::categories())) ? $request->category : null;
        $kurs = (float) ($request->kurs ?: ExchangeRate::usdToIdr()); // kurs USD→IDR terkini untuk grand total

        $base = Pipeline::query();
        if ($category) {
            $base->where('category', $category);
        }

        $rows = (clone $base)->with('outputs')->orderBy('category')->orderBy('id')->get();

        $totalIdr = (clone $base)->sum('amount_idr');
        $totalUsd = (clone $base)->sum('amount_usd');
        $grandIdr = $totalIdr + ($totalUsd * $kurs);

        $perAccount = (clone $base)
            ->selectRaw('account, SUM(amount_idr) idr, SUM(amount_usd) usd, COUNT(*) jml')
            ->groupBy('account')->get();

        $data = [
            'rows'       => $rows,
            'category'   => $category,
            'kurs'       => $kurs,
            'totalIdr'   => $totalIdr,
            'totalUsd'   => $totalUsd,
            'grandIdr'   => $grandIdr,
            'perAccount' => $perAccount,
            'generated'  => now()->format('d M Y H:i'),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pipelines.report', $data)
            ->setPaper('a4', 'landscape');

        $label = $category ? ucfirst($category) : 'Semua';

        return $pdf->stream("Report-Omzet-{$label}.pdf");
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

        return $request->validate([
            'category'        => ['required', \Illuminate\Validation\Rule::in(array_keys(Pipeline::categories()))],
            'account'         => 'required|in:fk,ai_preneur',
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
            'ke_gilang'       => 'required|in:belum,sudah,done',
            'catatan'         => 'nullable|string',
            'outputs'         => 'array',
            'outputs.*'       => 'exists:outputs,id',
        ]);
    }
}
