<?php

namespace App\Support;

use App\Models\BoardQuarterTarget;
use App\Models\InsightContent;
use App\Models\Objective;
use App\Models\Order;
use App\Models\Pipeline;
use App\Models\Transaction;
use App\Support\ExchangeRate;
use Illuminate\Support\Facades\DB;

/**
 * Realisasi metrik OKR otomatis, dihitung dari modul yang memang memilikinya:
 * Insight (view & subscriber) dan Pembukuan (omset).
 *
 *  Angkanya TIDAK PERNAH disimpan. Menyimpan salinan berarti ia bisa basi
 *  diam-diam saat data sumbernya dikoreksi, dan tak ada yang tahu kapan.
 *
 *  Pindahan utuh dari model `Okr` yang dihapus saat OKR jadi dua tingkat
 *  (Objective + Key Result) — logikanya tak berubah, tesnya tetap berlaku.
 *  Tempatnya di Support, sejalan dgn ExchangeRate & Quarter.
 */
final class OkrMetrics
{
    /** Metrik yang dikenal: key => label UI. Menambah metrik di sini WAJIB
     *  dibarengi cabang baru di realisasi(), kalau tidak angkanya diam-diam 0
     *  dan terbaca sbg "belum ada pencapaian" — bukan "belum didukung". */
    public const METRICS = [
        'view' => 'Views dari Insight',
        'subscriber' => 'Subscriber',
        'omset' => 'Omzet dari Pembukuan',
    ];

    /** Satuan tiap metrik, dipakai UI untuk memformat angka. */
    public const UNITS = [
        'view' => 'angka',
        'subscriber' => 'angka',
        'omset' => 'rupiah',
    ];

    /** Realisasi seluruh metrik pada satu kuartal: ['view' => 123, ...].
     *  Selalu memuat SEMUA key METRICS supaya pemanggil tak perlu null-check. */
    public static function realisasi(int $year, int $quarter): array
    {
        [$start, $end] = Quarter::range($year, $quarter);

        return [
            'view' => self::totalView($start, $end),
            'subscriber' => self::totalSubscriber($end),
            'omset' => self::totalOmset($start, $end),
            'omset_fk' => self::totalOmsetAccount(['fk'], $start, $end),
            'omset_aipreneur' => self::totalOmsetAccount(['ai_preneur', 'aipreneur'], $start, $end),
        ];
    }

    /** View = jumlah tayangan seluruh konten yang TERBIT di kuartal ini.
     *  Dasarnya published_at, bukan created_at: yang diukur performa konten
     *  periode itu, bukan kapan barisnya kebetulan masuk ke database. */
    private static function totalView($start, $end): float
    {
        return (float) InsightContent::whereBetween('published_at', [$start, $end])->sum('views');
    }

    /**
     * Subscriber = total pengikut seluruh akun pada snapshot TERAKHIR yang
     * masih ≤ akhir kuartal.
     *
     * Ini angka POSISI (berapa pengikut saat itu), bukan pertambahan — sesuai
     * cara target subscriber biasa ditulis ("tembus 100rb di Q3"). Karena
     * posisi, ia tidak boleh dibatasi awal kuartal: akun yang tak punya
     * snapshot baru di kuartal ini tetap punya pengikut, dan mengabaikannya
     * membuat total anjlok seolah pengikutnya hilang.
     *
     * Snapshot terakhir per akun dicari lewat subquery MAX(tanggal) yang
     * dikelompokkan per (platform, akun) — satu akun bisa punya banyak baris
     * harian, dan menjumlah semuanya akan menghitung orang yang sama
     * berulang kali.
     */
    private static function totalSubscriber($end): float
    {
        $terakhir = DB::table('insight_accounts')
            ->selectRaw('platform, akun, MAX(tanggal) as tanggal')
            ->where('tanggal', '<=', $end)
            ->groupBy('platform', 'akun');

        return (float) DB::table('insight_accounts as ia')
            ->joinSub($terakhir, 't', function ($join) {
                $join->on('ia.platform', '=', 't.platform')
                    ->on('ia.akun', '=', 't.akun')
                    ->on('ia.tanggal', '=', 't.tanggal');
            })
            ->sum('ia.followers');
    }

    /** Omset = seluruh transaksi pemasukan yang tanggalnya jatuh di kuartal.
     *  Sumbernya Pembukuan, bukan nilai deal di kartu Sales: kartu memuat
     *  estimasi/potensi yang belum tentu tertagih, sedangkan yang dijanjikan
     *  OKR adalah uang yang benar-benar masuk. */
    private static function totalOmset($start, $end): float
    {
        $pembukuan = (float) Transaction::where('type', 'pemasukan')
            ->whereBetween('date', [$start, $end])->sum('amount_idr');

        $rate = ExchangeRate::usdToIdr();
        $orders = Order::all()->filter(function ($o) use ($start, $end) {
            $t = $o->tanggal_bayar ?? $o->created_at;
            return $t && $t->gte($start) && $t->lte($end);
        });
        $orderTotal = (float) $orders->sum('total_idr') + (float) $orders->sum('total_usd') * $rate;

        return max($pembukuan, $orderTotal);
    }

    private static function totalOmsetAccount(array $accountKeys, $start, $end): float
    {
        $rate = ExchangeRate::usdToIdr();
        $orders = Order::whereIn('account', $accountKeys)->get()->filter(function ($o) use ($start, $end) {
            $t = $o->tanggal_bayar ?? $o->created_at;
            return $t && $t->gte($start) && $t->lte($end);
        });

        return (float) $orders->sum('total_idr') + (float) $orders->sum('total_usd') * $rate;
    }

    /** Progress % tiap Objective satu kuartal: [objectiveId => float|null].
     *
     *  Memakai sumber angka yang PERSIS sama dengan halaman OKR: realisasi +
     *  menyuntikkan angka board (kartu_selesai & target) ke KR sumber 'kartu'
     *  sebelum Objective::progress() dipanggil. OkrController menghitungnya
     *  inline (karena KR-nya juga dipakai untuk payload KR); di sini berdiri
     *  sendiri untuk konsumen ringan seperti preview Objective di Kanban —
     *  supaya angkanya tak pernah berbeda dari halaman OKR.
     */
    public static function objectiveProgress(int $year, int $quarter): array
    {
        $realisasi = self::realisasi($year, $quarter);
        $objectives = Objective::forQuarter($year, $quarter);

        $krKartu = $objectives->flatMap->keyResults->where('source', 'kartu');
        $boardKeys = $krKartu->pluck('board_key')->filter()->unique()->values();

        if ($boardKeys->isNotEmpty()) {
            $selesaiPerBoard = Pipeline::whereIn('category', $boardKeys)
                ->whereNotNull('completed_at')->where('is_kr_master', false)
                ->selectRaw('category, COUNT(*) as total')
                ->groupBy('category')->pluck('total', 'category');
            $targetPerBoard = BoardQuarterTarget::whereIn('board_key', $boardKeys)
                ->where('year', $year)->where('quarter', $quarter)
                ->pluck('target_done', 'board_key');

            foreach ($krKartu as $kr) {
                if ($kr->board_key) {
                    $kr->setAttribute('kartu_selesai', (int) ($selesaiPerBoard[$kr->board_key] ?? 0));
                    $kr->target = (string) ($targetPerBoard[$kr->board_key] ?? 0);
                }
            }
        }

        return $objectives->mapWithKeys(fn (Objective $o) => [$o->id => $o->progress($realisasi)])->all();
    }
}
