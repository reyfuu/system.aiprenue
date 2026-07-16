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

        // ---- Ringkasan atas & grafik: dari ORDER, bukan Sales ----
        // Sales = corong prospek (nilainya estimasi & bisa batal); Order = transaksi
        // yang benar-benar jadi. Jadi semua angka omzet di dashboard bersumber Order.
        // Alur bisnisnya: sales → order → kanban.
        $orders   = Order::all();
        $totalIdr = (float) $orders->sum('total_idr');
        $totalUsd = (float) $orders->sum('total_usd');
        $grandIdr = $totalIdr + $totalUsd * $rate;

        // Kartu modul Sales di bawah tetap pakai angkanya sendiri — di sana yang
        // ditanya "berapa nilai pipeline saya", dan itu memang estimasi.
        $pipe          = Pipeline::whereIn('category', array_keys($pipelineBoards))->get();
        $pipeEstimasi  = (float) $pipe->sum('amount_idr') + (float) $pipe->sum('amount_usd') * $rate;

        // ---- Omzet per akun (FK / AI Preneur) ----
        // Pecahan dari $grandIdr, bukan angka lain: FK + AI Preneur WAJIB = Grand Omzet.
        // Dihitung dari koleksi $orders yang sudah di-load → tanpa query tambahan.
        $perAccount = [];
        foreach (Order::ACCOUNTS as $key => $label) {
            $akun = $orders->where('account', $key);
            $perAccount[$key] = [
                'label'    => $label,
                'grandIdr' => (float) $akun->sum('total_idr') + (float) $akun->sum('total_usd') * $rate,
                'total'    => $akun->count(),
            ];
        }

        // ---- Omzet per bulan, dipecah per akun ----
        // Tanggalnya `tanggal_bayar` = kapan uang benar-benar masuk; itu arti "omzet
        // per bulan". Mundur ke `created_at` bila kosong supaya tak ada order yang
        // lenyap dari grafik tanpa jejak — jumlah semua bulan tetap = Grand Omzet.
        $perBulan = [];
        foreach ($orders as $o) {
            $tgl = $o->tanggal_bayar ?? $o->created_at;
            if (! $tgl) {
                continue;
            }
            $bulan = $tgl->format('Y-m');
            $perBulan[$bulan] ??= array_fill_keys(array_keys(Order::ACCOUNTS), 0.0);
            // akun di luar daftar (data lama) tetap dihitung, jangan didiamkan hilang
            $perBulan[$bulan][$o->account] = ($perBulan[$bulan][$o->account] ?? 0.0)
                + (float) $o->total_idr + (float) $o->total_usd * $rate;
        }
        ksort($perBulan);   // urut kronologis; array key 'Y-m' menyortir dgn benar sbg string

        $monthly = [];
        foreach ($perBulan as $bulan => $akun) {
            $monthly[] = [
                'label'      => \Carbon\Carbon::createFromFormat('Y-m', $bulan)->translatedFormat('M Y'),
                'perAccount' => array_map(fn ($v) => round($v), $akun),
                'total'      => round(array_sum($akun)),
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
            'rate'     => $rate,
            'monthly'  => $monthly,          // grafik omzet per bulan, per akun
            'accounts' => Order::ACCOUNTS,   // label + urutan seri grafik

            // Ringkasan atas — SEMUA dari Order (omzet nyata), bukan Sales (estimasi).
            // Satu baris = satu sumber; mencampur keduanya bikin angkanya tak bisa
            // dibandingkan satu sama lain.
            'summary' => [
                'grandIdr'    => $grandIdr,
                'perAccount'  => $perAccount,   // omzet FK & AI Preneur — pecahan grandIdr
                'total'       => $orders->count(),
                // Order cuma kenal full/dp — tak ada 'belum' spt kartu Sales.
                'lunas'       => $orders->where('tipe_pembayaran', 'full')->count(),
                'outstanding' => $orders->where('tipe_pembayaran', 'dp')->count(),
            ],

            'pipeline' => [
                'total'       => $pipe->count(),
                'grandIdr'    => $pipeEstimasi,   // estimasi Sales, BUKAN omzet Order
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
