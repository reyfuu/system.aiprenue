<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Board bawaan "Todolist" (idempotent: aman bila dijalankan ulang)
        DB::table('categories')->updateOrInsert(
            ['key' => 'todolist'],
            ['name' => 'Todolist', 'created_at' => $now, 'updated_at' => $now],
        );

        // Kolom khas todolist: To Do → Dikerjakan → Selesai
        $cols = [
            ['key' => 'todo', 'name' => 'To Do', 'color' => 'bg-slate-400'],
            ['key' => 'doing', 'name' => 'Dikerjakan', 'color' => 'bg-sky-500'],
            ['key' => 'done', 'name' => 'Selesai', 'color' => 'bg-emerald-500'],
        ];
        foreach ($cols as $i => $c) {
            DB::table('board_columns')->updateOrInsert(
                ['board_key' => 'todolist', 'key' => $c['key']],
                ['name' => $c['name'], 'color' => $c['color'], 'position' => $i, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        DB::table('board_columns')->where('board_key', 'todolist')->delete();
        DB::table('categories')->where('key', 'todolist')->delete();
    }
};
