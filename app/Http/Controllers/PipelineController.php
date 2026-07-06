<?php

namespace App\Http\Controllers;

use App\Models\Output;
use App\Models\Pipeline;
use App\Support\ExchangeRate;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function index(Request $request)
    {
        $categories = array_keys(Pipeline::CATEGORIES);
        $category = in_array($request->category, $categories) ? $request->category : 'endorse';

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

        return view('pipelines.index', [
            'pipelines' => $pipelines,
            'category'  => $category,
            'counts'    => $counts,
            'outputs'   => Output::orderBy('name')->get(),
            'summary'   => $summary,
            'filters'   => $request->only(['account', 'progress', 'payment_status', 'output', 'search']),
        ]);
    }

    public function report(Request $request)
    {
        $category = in_array($request->category, array_keys(Pipeline::CATEGORIES)) ? $request->category : null;
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

        return redirect()->route('pipelines.index')->with('status', 'Entri ditambahkan.');
    }

    public function update(Request $request, Pipeline $pipeline)
    {
        $data = $this->validated($request);
        $pipeline->update($data);
        $pipeline->outputs()->sync($request->input('outputs', []));

        return redirect()->route('pipelines.index')->with('status', 'Entri diperbarui.');
    }

    public function destroy(Pipeline $pipeline)
    {
        $pipeline->delete();

        return redirect()->route('pipelines.index')->with('status', 'Entri dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category'        => 'required|in:endorse,agensi,coaching,speaker',
            'account'         => 'required|in:fk,ai_preneur',
            'coaching'        => 'nullable|string|max:255',
            'speaker'         => 'nullable|string|max:255',
            'endorse'         => 'required|string|max:255',
            'progress'        => 'required|in:script,editing,progress,done,pending,tentatif',
            'tanggal_posting' => 'nullable|date',
            'tanggal_payment' => 'nullable|date',
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
