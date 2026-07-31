<?php

use App\Models\Pipeline;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Backfill completed_at untuk kartu yang sudah duduk di kolom terakhir board
 *  tapi belum tercatat selesai. Tanpa ini, kartu-kartu tersebut luput dari
 *  statistik OKR meskipun secara visual sudah berada di kolom "done"/"deal".
 *
 *  BoardColumn sengaja DB::table(), bukan Eloquent — sama alasan dgn migrasi
 *  lain: migrasi 2026_07_31_010000 (SoftDeletes) menambah `deleted_at` SESUDAH
 *  ini dalam riwayat, jadi query lewat model akan menabraknya saat
 *  `migrate:fresh` mengulang seluruh riwayat dari awal. */
return new class extends Migration
{
    public function up(): void
    {
        $boards = DB::table('board_columns')->orderBy('id')->get()->groupBy('board_key');

        foreach ($boards as $boardKey => $cols) {
            $lastCol = $cols->last()->key;

            Pipeline::where('category', $boardKey)
                ->where('progress', $lastCol)
                ->whereNull('completed_at')
                ->each(function (Pipeline $card) {
                    // Pakai updated_at sbg estimasi waktu selesai —
                    // lebih masuk akal daripada now() yg menimpa semuanya
                    // jadi "hari ini".
                    $card->updateQuietly(['completed_at' => $card->updated_at]);
                });
        }
    }
};
