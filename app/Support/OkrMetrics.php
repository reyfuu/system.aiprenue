<?php

namespace App\Support;

use App\Models\InsightContent;
use App\Models\Transaction;
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
        'view' => 'View',
        'subscriber' => 'Subscriber',
        'omset' => 'Omset',
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
        return (float) Transaction::where('type', 'pemasukan')
            ->whereBetween('date', [$start, $end])->sum('amount_idr');
    }
}
