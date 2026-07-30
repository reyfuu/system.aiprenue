<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Petakan role lama → 3 role baru (owner/manager/it).
    private array $map = [
        'super_admin' => 'owner',
        'admin'       => 'manager',
        'editor'      => 'manager',
        'staff'       => 'manager',
        'it'          => 'it',
    ];

    public function up(): void
    {
        foreach ($this->map as $old => $new) {
            DB::table('users')->where('role', $old)->update(['role' => $new]);
        }
        // Sisa role tak dikenal → manager (fallback aman)
        DB::table('users')->whereNotIn('role', ['owner', 'manager', 'it'])->update(['role' => 'manager']);
    }

    public function down(): void
    {
        // owner → super_admin; manager → staff (perkiraan; editor/admin tak bisa dibalik presisi)
        DB::table('users')->where('role', 'owner')->update(['role' => 'super_admin']);
        DB::table('users')->where('role', 'manager')->update(['role' => 'staff']);
    }
};
