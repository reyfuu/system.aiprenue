<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buka menu `kpi` untuk SEMUA peran, termasuk staff.
 *
 *  Halaman KPI kini punya dua tab. Tab "Per Board" tetap hanya untuk peran
 *  pengelola (canManage) — datanya bahkan tak dikirim ke yang lain. Tab
 *  "Per Orang" menampilkan seluruh tim bagi owner & manager, dan HANYA baris
 *  dirinya sendiri bagi peran lain. Orang berhak tahu penilaian atas dirinya
 *  tanpa bisa membanding-bandingkan rekan.
 *
 *  ⚠️ Konsekuensi yang harus disadari: sesudah migrasi ini `canSee('kpi')`
 *  bernilai true untuk staff. Panel capaian kuartal di halaman Kanban karena
 *  itu digerbangi `canManage()`, BUKAN `canSee('kpi')` — lihat
 *  PipelineController::renderBoard(). Menukar keduanya akan membocorkan rapor
 *  board ke staff lagi.
 */
return new class extends Migration
{
    private const PERAN = ['owner', 'it', 'manager', 'admin', 'staff'];

    public function up(): void
    {
        if (! Schema::hasTable('role_menu_access')) {
            return;
        }

        $now = now();
        DB::table('role_menu_access')->insertOrIgnore(array_map(fn ($role) => [
            'role' => $role,
            'menu' => 'kpi',
            'created_at' => $now,
            'updated_at' => $now,
        ], self::PERAN));
    }

    public function down(): void
    {
        if (! Schema::hasTable('role_menu_access')) {
            return;
        }

        // Kembali ke keadaan sebelum rapor per orang: staff tanpa menu KPI.
        DB::table('role_menu_access')->where('menu', 'kpi')->where('role', 'staff')->delete();
    }
};
