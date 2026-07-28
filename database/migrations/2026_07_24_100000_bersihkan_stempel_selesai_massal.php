<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bersihkan stempel `completed_at` yang terpasang massal oleh bug drag.
 *
 *  Bug-nya: kiriman drag berisi SELURUH isi kolom tujuan, dan reorder() dulu
 *  menstempel setiap kartu di kiriman itu. Satu kartu yang di-drag ke kolom
 *  "Selesai" ikut menstempel semua kartu lama yang sudah lama duduk di sana
 *  dengan waktu "sekarang" — deadline mereka sudah lewat berbulan-bulan, jadi
 *  seluruh papan mendadak terbaca terlambat. Sudah diperbaiki di
 *  PipelineController::reorder(); migrasi ini membereskan jejaknya.
 *
 *  Yang dikosongkan HANYA kartu yang memenuhi KEDUA syarat:
 *    1. stempelnya jatuh pada/sesudah 2026-07-23 — tanggal kolom completed_at
 *       ditambahkan. Sebelum itu kolomnya belum ada, jadi stempel apa pun yang
 *       lebih tua mustahil berasal dari bug ini.
 *    2. selisih stempel dgn deadline > 30 hari. Kartu yang benar-benar telat
 *       beberapa hari terlihat sama persis dgn korban bug bila hanya syarat (1)
 *       yang dipakai — ambang ini menyisakan mereka.
 *
 *  Dikosongkan, bukan diisi dgn tanggal tebakan: kapan kartu itu sebenarnya
 *  selesai TIDAK PERNAH tercatat. Mengisinya dgn deadline akan membuat kartu
 *  yang dulu memang telat terbaca tepat waktu — mengganti angka salah dgn
 *  angka salah yang lebih enak dipandang. Kartu tanpa stempel jatuh ke 'lewat'
 *  dan tak ikut hitungan rasio ketepatan sama sekali; itu jawaban jujur untuk
 *  data yang memang tak diketahui.
 *
 *  Tak ada down(): nilai yang dihapus adalah data karangan. Mengembalikannya
 *  berarti menanam ulang angka yang salah.
 */
return new class extends Migration
{
    /** Tanggal kolom completed_at ditambahkan (2026_07_23_100000). */
    private const DIPASANG = '2026-07-23 00:00:00';

    private const AMBANG_HARI = 30;

    public function up(): void
    {
        if (! Schema::hasColumn('pipelines', 'completed_at')) {
            return;
        }

        // DATEDIFF dipakai lewat whereRaw karena membandingkan dua KOLOM, bukan
        // kolom dgn nilai — tak ada bentuk query builder untuk itu. Sintaksnya
        // MySQL/MariaDB; SQLite (dipakai tes) memakai julianday(). Dipilih per
        // driver supaya migrasi tetap jalan di kedua tempat.
        $selisihHari = DB::connection()->getDriverName() === 'sqlite'
            ? 'julianday(completed_at) - julianday(deadline)'
            : 'DATEDIFF(completed_at, deadline)';

        $terdampak = DB::table('pipelines')
            ->whereNotNull('completed_at')
            ->whereNotNull('deadline')
            ->where('completed_at', '>=', self::DIPASANG)
            ->whereRaw("$selisihHari > ?", [self::AMBANG_HARI])
            ->update(['completed_at' => null]);

        if ($terdampak > 0) {
            // Disebut terang-terangan: ini menghapus data, dan orang yang
            // menjalankan migrasi berhak tahu berapa banyak.
            echo "  Stempel selesai dikosongkan pada $terdampak kartu (terpasang massal oleh bug drag).".PHP_EOL;
        }
    }

    public function down(): void
    {
        // Sengaja kosong — lihat catatan di atas.
    }
};
