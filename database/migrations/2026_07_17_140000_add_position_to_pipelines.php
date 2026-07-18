<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Urutan kartu di dalam kolom kanban.
 *
 *  Sebelumnya kartu diurutkan `orderBy('id')` — urutan pembuatan. Efeknya
 *  menggeser kartu naik/turun terlihat berhasil di layar lalu balik ke tempat
 *  semula begitu halaman dimuat ulang, karena tak ada tempat menyimpannya.
 *
 *  Backfill `position = id`: urutan yang tampil hari ini PERSIS sama sesudah
 *  migrasi. Kalau diisi 0 semua, tie-break jatuh ke id & kebetulan sama —
 *  tapi cuma kebetulan; begitu satu kartu digeser, sisanya ikut acak. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pipelines', 'position')) {
            return;   // skema server pernah menyimpang dari catatan migrasi
        }

        Schema::table('pipelines', function (Blueprint $table) {
            // default 0: kartu baru muncul di puncak kolomnya. Sengaja — kartu
            // yang baru dibuat itu yang sedang dikerjakan, bukan yang terlupakan
            // di dasar tumpukan.
            $table->unsignedInteger('position')->default(0)->after('progress');
        });

        // Pertahankan urutan yang sedang dilihat orang.
        DB::table('pipelines')->update(['position' => DB::raw('id')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('pipelines', 'position')) {
            Schema::table('pipelines', fn (Blueprint $table) => $table->dropColumn('position'));
        }
    }
};
