<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom divisi/tim untuk Objective — dipakai dashboard OKR (kartu "Helicopter"
 * mengelompokkan objective per divisi). Nullable & bebas teks: aplikasi belum
 * punya tabel tim/divisi formal, jadi ini label ringan yang diisi di form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('objectives', function (Blueprint $table) {
            $table->string('division')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('objectives', function (Blueprint $table) {
            $table->dropColumn('division');
        });
    }
};
