<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tambah kolom deskripsi pada Key Result. */
    public function up(): void
    {
        Schema::table('key_results', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('key_results', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
