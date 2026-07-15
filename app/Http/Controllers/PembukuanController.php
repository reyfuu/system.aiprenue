<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Transaction;
use Carbon\Carbon;
use Inertia\Inertia;

class PembukuanController extends Controller
{
    /** Rekap keuangan dari transaksi (pemasukan/pengeluaran) + inventaris. */
    private function build(): array
    {
        $tx = Transaction::orderBy('date')->get();
        $inv = Inventory::orderBy('month')->orderBy('name')->get();

        // rekap per bulan
        $byMonth = [];
        foreach ($tx as $t) {
            $key = $t->date->format('Y-m');
            $byMonth[$key] ??= ['pemasukan' => 0, 'pengeluaran' => 0];
            $byMonth[$key][$t->type] += (float) $t->amount_idr;
        }
        ksort($byMonth);
        $monthly = [];
        foreach ($byMonth as $key => $m) {
            $monthly[] = [
                'label'       => Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                'pemasukan'   => round($m['pemasukan']),
                'pengeluaran' => round($m['pengeluaran']),
                'laba'        => round($m['pemasukan'] - $m['pengeluaran']),
            ];
        }

        $totalIn = (float) $tx->where('type', 'pemasukan')->sum('amount_idr');
        $totalOut = (float) $tx->where('type', 'pengeluaran')->sum('amount_idr');

        $catRows = fn ($type) => $tx->where('type', $type)
            ->groupBy('category')
            ->map(fn ($g, $cat) => ['label' => $cat, 'value' => round($g->sum('amount_idr'))])
            ->sortByDesc('value')->values()->all();

        // inventaris: snapshot bulan terakhir
        $latestMonth = $inv->max('month');
        $latestInv = $latestMonth ? $inv->filter(fn ($i) => $i->month->equalTo($latestMonth)) : collect();
        $inventory = $latestInv->map(fn ($i) => [
            'name'  => $i->name,
            'qty'   => $i->qty,
            'unit'  => round((float) $i->unit_value_idr),
            'total' => round($i->total_value),
        ])->values()->all();
        $invTotal = $latestInv->sum(fn ($i) => $i->total_value);

        return [
            'summary' => [
                'totalIn'       => round($totalIn),
                'totalOut'      => round($totalOut),
                'laba'          => round($totalIn - $totalOut),
                'invTotal'      => round($invTotal),
                'invMonthLabel' => $latestMonth ? $latestMonth->format('M Y') : '—',
                'generated'     => now()->format('d M Y H:i'),
            ],
            'monthly'      => $monthly,
            'incomeByCat'  => $catRows('pemasukan'),
            'expenseByCat' => $catRows('pengeluaran'),
            'inventory'    => $inventory,
            'reportUrl'    => route('pembukuan.report'),
        ];
    }

    public function index()
    {
        return Inertia::render('Pembukuan', ['payload' => $this->build()]);
    }

    public function report()
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pembukuan.report', ['d' => $this->build()])
            ->setPaper('a4', 'portrait');

        return $pdf->stream('Pembukuan-Keuangan.pdf');
    }
}
