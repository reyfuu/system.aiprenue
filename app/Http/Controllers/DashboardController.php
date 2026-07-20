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
    public function index(\Illuminate\Http\Request $request)
    {
        $rate = ExchangeRate::usdToIdr();

        $pipelineBoards = Pipeline::categories('pipeline');
        $kanbanBoards   = Pipeline::categories('kanban');

        // ---- Ringkasan atas & grafik: dari ORDER, bukan Sales ----
        // Sales = corong prospek (nilainya estimasi & bisa batal); Order = transaksi
        // yang benar-benar jadi. Jadi semua angka omzet di dashboard bersumber Order.
        // Alur bisnisnya: sales → order → kanban.
        $semuaOrder = Order::all();

        // Tanggal acuan satu order = kapan uangnya masuk. Didefinisikan SEKALI di
        // sini & dipakai filter maupun grafik — kalau dua tempat memakai aturan
        // berbeda, total per bulan tak akan pernah cocok dgn Grand Omzet.
        $tanggalOrder = fn ($o) => $o->tanggal_bayar ?? $o->created_at;

        // Daftar bulan yang benar-benar punya order (untuk isi dropdown).
        $bulanTersedia = $semuaOrder
            ->map(fn ($o) => ($t = $tanggalOrder($o)) ? $t->format('Y-m') : null)
            ->filter()->unique()->sort()->reverse()->values();

        // Filter bulan. Default 'semua' — SENGAJA, bukan bulan berjalan: mengubah
        // default berarti angka yang selama ini dilihat orang tiba-tiba mengecil
        // tanpa mereka mengubah apa pun.
        $bulanAktif = (string) $request->query('bulan', 'semua');
        if (! $bulanTersedia->contains($bulanAktif)) {
            $bulanAktif = 'semua';   // bulan ngawur / tanpa data → jangan tampilkan kosong diam-diam
        }

        $orders = $bulanAktif === 'semua'
            ? $semuaOrder
            : $semuaOrder->filter(fn ($o) => ($t = $tanggalOrder($o)) && $t->format('Y-m') === $bulanAktif);

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
        // Sengaja dari $semuaOrder, BUKAN $orders yang terfilter: grafik ini justru
        // pembanding antar bulan. Kalau ikut terfilter, isinya tinggal satu titik &
        // kehilangan seluruh gunanya. Filter mengecilkan angka ringkasan, bukan tren.
        $perBulan = [];
        foreach ($semuaOrder as $o) {
            $tgl = $tanggalOrder($o);
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
            'monthly'  => $monthly,          // grafik omzet per bulan, per akun (SELALU semua bulan)
            'accounts' => Order::ACCOUNTS,   // label + urutan seri grafik

            // Filter periode. `opsi` cuma memuat bulan yang benar-benar ada ordernya,
            // jadi tak ada pilihan yang menghasilkan halaman kosong.
            'filter' => [
                'bulan' => $bulanAktif,
                'opsi'  => $bulanTersedia->map(fn ($b) => [
                    'value' => $b,
                    'label' => \Carbon\Carbon::createFromFormat('Y-m', $b)->translatedFormat('F Y'),
                ])->values(),
            ],

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

            // Ikut filter bulan seperti ringkasan atas — kartu ini bicara tentang
            // order yang sama, jadi kalau angkanya beda periode orang akan
            // membandingkan dua hal yang tak sebanding. Dihitung dari koleksi
            // $orders yang sudah di-load, bukan query ulang.
            'order' => [
                'total'     => $orders->count(),
                'dp'        => $orders->where('tipe_pembayaran', 'dp')->count(),
                // Nilai order = IDR + USD dikonversi kurs (prioritas sudah dibuang)
                'nilai'     => (float) $orders->sum('total_idr') + (float) $orders->sum('total_usd') * $rate,
                'perTipe'   => $orders->groupBy('tipe_order')->map->count(),
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
