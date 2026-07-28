<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan kartu Kanban → Key Result: satu kartu adalah langkah menuju satu KR.
 *
 *  Ini yang menghubungkan menu OKR dgn papan kerja: goal per kuartal ditulis
 *  sbg Objective + Key Result di /okr, lalu langkah-langkah untuk mencapainya
 *  dibuat sbg kartu di Kanban todolist dan ditautkan ke KR-nya. KR bersumber
 *  'kartu' menghitung realisasinya dari kartu tautan yang sudah selesai.
 *
 *  nullOnDelete, BUKAN cascade: menghapus sebuah Key Result tak boleh ikut
 *  menghapus kartunya — pekerjaan yang sudah dilakukan tetap ada, ia cuma
 *  kehilangan kaitan ke goal yang mungkin sudah dibatalkan. Kartu tetap hidup
 *  di papannya.
 *
 *  Idempoten per kolom, mengikuti pola migrasi created_by/completed_at: skema
 *  server pernah menyimpang dari catatan migrasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pipelines', 'key_result_id')) {
            return;
        }

        Schema::table('pipelines', function (Blueprint $table) {
            // Ikut di-index: menghitung kartu-selesai per KR menyaring lewat
            // kolom ini pada tiap render halaman OKR.
            $table->foreignId('key_result_id')->nullable()->after('assigned_to')
                ->constrained('key_results')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pipelines', 'key_result_id')) {
            return;
        }

        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('key_result_id');
        });
    }
};
