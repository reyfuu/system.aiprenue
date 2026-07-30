<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Pembukuan bersifat tetap: hanya Owner dan Manager yang boleh melihat. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_menu_access')->where('menu', 'pembukuan')
            ->whereNotIn('role', ['owner', 'manager'])->delete();

        foreach (['owner', 'manager'] as $role) {
            DB::table('role_menu_access')->insertOrIgnore([
                'role' => $role,
                'menu' => 'pembukuan',
                'can_manage' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Tidak mengarang ulang izin lama yang mungkin sudah diubah pengguna.
    }
};
