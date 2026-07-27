<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** OKR: satu Objective (sasaran) punya banyak Key Result (ukuran keberhasilan).
 *  Key result dilacak sederhana: selesai / belum (bukan angka target-capaian).
 *  Progress objective = % key result yang sudah selesai (dihitung di UI). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('okr_objectives', function (Blueprint $table) {
            $table->id();
            $table->string('title');                 // sasaran (kualitatif)
            $table->string('period')->nullable();    // mis. "Q3 2026" — bebas diketik
            $table->string('owner')->nullable();     // penanggung jawab (teks bebas)
            $table->date('deadline')->nullable();    // batas waktu — dipajang di kartu
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('okr_key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objective_id')->constrained('okr_objectives')->cascadeOnDelete();
            $table->string('title');                     // ukuran keberhasilan
            $table->boolean('completed')->default(false); // selesai / belum
            $table->timestamps();
        });

        // Menu baru langsung tersedia bagi tim pengelola; staff tetap tanpa akses.
        // (Owner selalu lihat lewat pagar di User::canSee, tak butuh baris ini.)
        if (Schema::hasTable('role_menu_access')) {
            $now = now();
            DB::table('role_menu_access')->insertOrIgnore(array_map(fn ($role) => [
                'role' => $role,
                'menu' => 'okr',
                'created_at' => $now,
                'updated_at' => $now,
            ], ['owner', 'it', 'manager', 'admin']));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_menu_access')) {
            DB::table('role_menu_access')->where('menu', 'okr')->delete();
        }

        Schema::dropIfExists('okr_key_results');
        Schema::dropIfExists('okr_objectives');
    }
};
