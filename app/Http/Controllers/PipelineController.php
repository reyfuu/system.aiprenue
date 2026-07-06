<?php

namespace App\Http\Controllers;

use App\Models\Output;
use App\Models\Pipeline;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function index(Request $request)
    {
        $query = Pipeline::query()->with('outputs');

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

        $summary = [
            'total_idr'       => Pipeline::sum('amount_idr'),
            'total_usd'       => Pipeline::sum('amount_usd'),
            'lunas'           => Pipeline::where('payment_status', 'lunas')->count(),
            'outstanding'     => Pipeline::whereIn('payment_status', ['belum', 'dp'])->count(),
            'outstanding_idr' => Pipeline::whereIn('payment_status', ['belum', 'dp'])->sum('amount_idr'),
            'outstanding_usd' => Pipeline::whereIn('payment_status', ['belum', 'dp'])->sum('amount_usd'),
            'done'            => Pipeline::where('progress', 'done')->count(),
            'total'           => Pipeline::count(),
        ];

        return view('pipelines.index', [
            'pipelines' => $pipelines,
            'outputs'   => Output::orderBy('name')->get(),
            'summary'   => $summary,
            'filters'   => $request->only(['account', 'progress', 'payment_status', 'output', 'search']),
        ]);
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
            'account'         => 'required|in:fk,ai_preneur',
            'coaching'        => 'nullable|string|max:255',
            'speaker'         => 'nullable|string|max:255',
            'endorse'         => 'required|string|max:255',
            'progress'        => 'required|in:editing,progress,done',
            'tanggal_posting' => 'nullable|date',
            'tanggal_payment' => 'nullable|date',
            'payment_status'  => 'required|in:belum,dp,lunas',
            'amount_idr'      => 'nullable|numeric|min:0',
            'amount_usd'      => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
            'outputs'         => 'array',
            'outputs.*'       => 'exists:outputs,id',
        ]);
    }
}
