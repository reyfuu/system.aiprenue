<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Pisahkan KPI board dari OKR perusahaan menjadi dua menu.
 *
 *  Alasannya bukan kerapian, tapi hak akses: OKR memuat omset & pertumbuhan
 *  audiens — angka yang hanya untuk owner dan manager. KPI board (berapa kartu
 *  selesai, siapa telat) adalah data operasional yang justru berguna bagi tim
 *  yang mengelola papannya. Selama keduanya satu halaman, memberi akses ke
 *  yang satu berarti memberi akses ke yang lain.
 *
 *  Menu 'okr' TIDAK dihapus dari role_menu_access, tapi barisnya dipangkas ke
 *  owner & manager saja. Penegakan sebenarnya ada di User::canSee() yang
 *  mengunci 'okr' ke dua peran itu (sejalan dgn pembukuan & tracking) —
 *  pemangkasan di sini supaya halaman Manajemen Akses tak menampilkan centang
 *  yang sudah tercentang padahal tak berpengaruh. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_menu_access')) {
            return;
        }

        $now = now();

        // KPI board: tim pengelola papan. Staff tetap di luar — ia view-only
        // dan tak menetapkan target.
        DB::table('role_menu_access')->insertOrIgnore(array_map(fn ($role) => [
            'role' => $role,
            'menu' => 'kpi',
            'created_at' => $now,
            'updated_at' => $now,
        ], ['owner', 'it', 'manager', 'admin']));

        // OKR menyusut ke owner & manager.
        DB::table('role_menu_access')
            ->where('menu', 'okr')
            ->whereNotIn('role', ['owner', 'manager'])
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('role_menu_access')) {
            return;
        }

        DB::table('role_menu_access')->where('menu', 'kpi')->delete();

        // Kembalikan akses OKR untuk 'it' seperti sebelum pemisahan.
        $now = now();
        DB::table('role_menu_access')->insertOrIgnore([
            ['role' => 'it', 'menu' => 'okr', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};
