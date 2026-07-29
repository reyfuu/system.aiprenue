<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot penanda Urgent/Penting untuk item OKR.
 *
 * Bentuk JSON mengikuti `pipelines.labels`: nama dan warna disimpan bersama
 * agar badge OKR lama tidak berubah bila definisi label kemudian diubah atau
 * dihapus dari daftar pilihan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('objectives') && ! Schema::hasColumn('objectives', 'priority')) {
            Schema::table('objectives', function (Blueprint $table): void {
                $table->json('priority')->nullable()->after('description');
            });
        }

        if (Schema::hasTable('key_results') && ! Schema::hasColumn('key_results', 'priority')) {
            Schema::table('key_results', function (Blueprint $table): void {
                $table->json('priority')->nullable()->after('unit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('objectives') && Schema::hasColumn('objectives', 'priority')) {
            Schema::table('objectives', function (Blueprint $table): void {
                $table->dropColumn('priority');
            });
        }

        if (Schema::hasTable('key_results') && Schema::hasColumn('key_results', 'priority')) {
            Schema::table('key_results', function (Blueprint $table): void {
                $table->dropColumn('priority');
            });
        }
    }
};
