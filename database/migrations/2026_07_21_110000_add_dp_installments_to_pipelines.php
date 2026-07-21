<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cicilan DP di kartu sales: DP1, DP2, DP3.
 *
 *  Tiga slot tetap (bukan JSON array N cicilan) — deal di sini bayarnya paling
 *  banyak tiga termin, dan tiga kolom lebih gampang divalidasi & di-query dari
 *  pada array. Semua nullable: kartu lama belum ada DP, dan tak semua deal DP
 *  penuh sampai tiga kali. Nominal IDR (mengikuti amount_idr).
 *
 *  Idempoten per kolom, mengikuti add_contact_to_pipelines. */
return new class extends Migration
{
    private const COLUMNS = ['dp1', 'dp2', 'dp3'];

    public function up(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            $after = 'amount_usd';
            foreach (self::COLUMNS as $col) {
                if (! Schema::hasColumn('pipelines', $col)) {
                    $table->decimal($col, 15, 2)->nullable()->after($after);
                }
                $after = $col;
            }
        });
    }

    public function down(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            foreach (self::COLUMNS as $col) {
                if (Schema::hasColumn('pipelines', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
