<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Tracking eksekutif hanya untuk Owner dan Manager. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['owner', 'manager'] as $role) {
            DB::table('role_menu_access')->insertOrIgnore([
                'role' => $role,
                'menu' => 'tracking',
                'can_manage' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role_menu_access')->where('menu', 'tracking')->delete();
    }
};
