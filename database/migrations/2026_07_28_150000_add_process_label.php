<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('labels')->where('name', 'Process')->exists()) {
            DB::table('labels')->insert([
                'name' => 'Process',
                'color' => 'bg-brand-600',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('labels')->where('name', 'Process')->delete();
    }
};
