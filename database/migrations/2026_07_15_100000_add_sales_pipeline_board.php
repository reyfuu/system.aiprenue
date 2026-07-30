<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Board 'sales' (tipe pipeline) + stage sales.
     *  Board pipeline lama (endorse/coaching/agensi/speaker) tak disentuh —
     *  kolom produksinya tetap, cuma UI-nya sekarang board, bukan tabel. */
    public function up(): void
    {
        if (DB::table('categories')->where('key', 'sales')->exists()) {
            return; // idempoten: aman dijalankan ulang di server
        }

        $now = now();
        DB::table('categories')->insert([
            'key' => 'sales', 'name' => 'Sales', 'type' => 'pipeline',
            'section' => null, 'super_admin_only' => false,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // Stage sales. `key` dipakai sbg pipelines.progress → jangan diubah sembarangan.
        // Warna dot header wajib ada di safelist app.css (@source inline).
        $stages = [
            ['key' => 'lead',    'name' => 'Lead',    'color' => 'bg-slate-400'],
            ['key' => 'kontak',  'name' => 'Kontak',  'color' => 'bg-sky-500'],
            ['key' => 'nego',    'name' => 'Nego',    'color' => 'bg-amber-500'],
            ['key' => 'closing', 'name' => 'Closing', 'color' => 'bg-brand-600'],
            ['key' => 'deal',    'name' => 'Deal',    'color' => 'bg-emerald-500'],
        ];
        foreach ($stages as $i => $s) {
            DB::table('board_columns')->insert([
                'board_key' => 'sales', 'key' => $s['key'], 'name' => $s['name'],
                'color' => $s['color'], 'position' => $i,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('board_columns')->where('board_key', 'sales')->delete();
        DB::table('categories')->where('key', 'sales')->delete();
    }
};
