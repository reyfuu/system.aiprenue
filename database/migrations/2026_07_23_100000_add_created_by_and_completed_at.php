<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jejak pembuat & waktu selesai — dua hal yang selama ini tak pernah dicatat.
 *
 *  `created_by` (kartu & board): siapa yang membuatnya. nullOnDelete, BUKAN
 *  cascade — menghapus user tak boleh ikut menghapus kartunya; yang hilang
 *  cukup namanya saja. Semua baris lama bernilai null dan memang begitu
 *  adanya: datanya tak pernah ada, jadi jangan dikarang lewat backfill.
 *
 *  `completed_at` (kartu): KAPAN kartu jadi selesai, bukan sekadar `done`
 *  bernilai true. Tanpa stempel waktu ini, "terlambat atau tepat waktu" tak
 *  bisa dijawab sama sekali — `updated_at` tidak dipakai karena ia bergeser
 *  tiap kali kartu disunting, termasuk lama sesudah pekerjaannya rampung.
 *
 *  Idempoten per kolom, mengikuti pola migrasi kontak: skema server pernah
 *  menyimpang dari catatan migrasi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            if (! Schema::hasColumn('pipelines', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('assigned_to')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('pipelines', 'completed_at')) {
                // Ikut di-index: analitik ketepatan menyaring per rentang tanggal.
                $table->timestamp('completed_at')->nullable()->after('done')->index();
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'created_by')) {
                $table->foreignId('created_by')->nullable()
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            if (Schema::hasColumn('pipelines', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('pipelines', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
