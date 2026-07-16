<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Mindmap;
use App\Models\Order;
use App\Models\Pipeline;
use App\Models\Transaction;
use App\Support\ExchangeRate;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $rate = ExchangeRate::usdToIdr();

        $pipelineBoards = Pipeline::categories('pipeline');
        $kanbanBoards   = Pipeline::categories('kanban');

        // ---- Pipeline: entri di board bertipe 'pipeline' ----
        $pipe     = Pipeline::whereIn('category', array_keys($pipelineBoards))->get();
        $totalIdr = (float) $pipe->sum('amount_idr');
        $totalUsd = (float) $pipe->sum('amount_usd');
        $grandIdr = $totalIdr + $totalUsd * $rate;

        // ---- Omzet per akun (FK / AI Preneur) ----
        // Pecahan dari $grandIdr, bukan angka lain: FK + AI Preneur WAJIB = Grand Omzet.
        // Dihitung dari koleksi $pipe yang sudah di-load → tanpa query tambahan.
        $perAccount = [];
        foreach (Pipeline::ACCOUNTS as $key => $label) {
            $akun = $pipe->where('account', $key);
            $perAccount[$key] = [
                'label'    => $label,
                'grandIdr' => (float) $akun->sum('amount_idr') + (float) $akun->sum('amount_usd') * $rate,
                'total'    => $akun->count(),
            ];
        }

        // ---- Kanban: task di board bertipe 'kanban' (BUKAN entri pipeline) ----
        $kanban = Pipeline::whereIn('category', array_keys($kanbanBoards))->get();

        // ---- Script: folder & naskah di public/scripts (dotfile spt .gitkeep diabaikan) ----
        $scriptDir     = public_path('scripts');
        $scriptFolders = File::isDirectory($scriptDir) ? count(File::directories($scriptDir)) : 0;
        $scriptFiles   = File::isDirectory($scriptDir)
            ? count(array_filter(File::allFiles($scriptDir), fn ($f) => ! str_starts_with($f->getFilename(), '.')))
            : 0;

        // ---- Pembukuan: dari transaksi & inventaris (bukan omzet pipeline) ----
        $pemasukan   = (float) Transaction::where('type', 'pemasukan')->sum('amount_idr');
        $pengeluaran = (float) Transaction::where('type', 'pengeluaran')->sum('amount_idr');
        $invTotal    = Inventory::get(['qty', 'unit_value_idr'])->sum(fn ($i) => $i->qty * (float) $i->unit_value_idr);

        return Inertia::render('Dashboard', [
            'rate' => $rate,

            // Ringkasan atas — angka bisnis pipeline
            'summary' => [
                'grandIdr'    => $grandIdr,
                'perAccount'  => $perAccount,   // omzet FK & AI Preneur — pecahan grandIdr
                'total'       => $pipe->count(),
                'lunas'       => $pipe->where('payment_status', 'lunas')->count(),
                'outstanding' => $pipe->whereIn('payment_status', ['belum', 'dp'])->count(),
            ],

            'pipeline' => [
                'total'       => $pipe->count(),
                'grandIdr'    => $grandIdr,
                // Board pipeline kini cuma satu (sales) → pecah per JENIS deal,
                // bukan per board. Dashboard.vue tetap: loop `categories`, baca `perCategory`.
                'perCategory' => $pipe->groupBy('jenis')->map->count(),
                'categories'  => Pipeline::JENIS,
            ],

            'kanban' => [
                'total'       => $kanban->count(),
                'done'        => $kanban->where('progress', 'done')->count(),
                'boards'      => count($kanbanBoards),
                'perProgress' => $kanban->groupBy('progress')->map->count(),
                'progresses'  => Pipeline::PROGRESS,
            ],

            'order' => [
                'total'     => Order::count(),
                'dp'        => Order::where('tipe_pembayaran', 'dp')->count(),
                // Nilai order = IDR + USD dikonversi kurs (prioritas sudah dibuang)
                'nilai'     => (float) Order::sum('total_idr') + (float) Order::sum('total_usd') * $rate,
                'perTipe'   => Order::selectRaw('tipe_order, count(*) as total')->groupBy('tipe_order')->pluck('total', 'tipe_order'),
                'tipeOrder' => Order::TIPE_ORDER,
            ],

            'mindmap' => [
                'total'  => Mindmap::count(),
                'latest' => Mindmap::latest('updated_at')->value('title'),
            ],

            'script' => [
                'folders' => $scriptFolders,
                'files'   => $scriptFiles,
            ],

            'pembukuan' => [
                'pemasukan'   => $pemasukan,
                'pengeluaran' => $pengeluaran,
                'laba'        => $pemasukan - $pengeluaran,
                'transaksi'   => Transaction::count(),
                'invTotal'    => (float) $invTotal,
            ],
        ]);
    }
}
