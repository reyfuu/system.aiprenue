<?php

use App\Models\BoardColumn;
use App\Models\Pipeline;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Backfill completed_at untuk kartu yang sudah duduk di kolom terakhir board
 *  tapi belum tercatat selesai. Tanpa ini, kartu-kartu tersebut luput dari
 *  statistik OKR meskipun secara visual sudah berada di kolom "done"/"deal". */
return new class extends Migration
{
    public function up(): void
    {
        $boards = BoardColumn::all()->groupBy('board_key');

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
