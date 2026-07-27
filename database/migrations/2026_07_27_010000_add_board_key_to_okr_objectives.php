<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tautan objective → board Kanban yang dibuat otomatis (Category.key).
 *  Nullable: objective lama / gagal-sinkron tetap sah tanpa board. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('okr_objectives', function (Blueprint $table) {
            $table->string('board_key')->nullable()->after('deadline');
        });
    }

    public function down(): void
    {
        Schema::table('okr_objectives', function (Blueprint $table) {
            $table->dropColumn('board_key');
        });
    }
};
