<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan kartu Kanban ke Key Result.
 *
 * Kolom dibuat nullable agar kartu lama tetap valid. Menghapus Key Result hanya
 * melepas tautannya; kartu Kanban tidak ikut terhapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pipelines', 'key_result_id')) {
            return;
        }

        Schema::table('pipelines', function (Blueprint $table) {
            $table->foreignId('key_result_id')
                ->nullable()
                ->after('assigned_to')
                ->constrained('key_results')
                ->nullOnDelete();
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
