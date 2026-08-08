<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Follow Up 1, Follow Up 2, Follow Up 3 di kartu sales.
 *
 *  Tiga slot tanggal follow up untuk melacak kapan lead/deal dikontak kembali.
 *  Disimpan bertipe `date` (nullable) setelah kolom `dp3`.
 */
return new class extends Migration
{
    private const COLUMNS = ['follow_up1', 'follow_up2', 'follow_up3'];

    public function up(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            $after = 'dp3';
            foreach (self::COLUMNS as $col) {
                if (! Schema::hasColumn('pipelines', $col)) {
                    $table->date($col)->nullable()->after($after);
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
