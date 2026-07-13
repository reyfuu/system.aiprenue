<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_columns', function (Blueprint $table) {
            $table->id();
            $table->string('board_key')->index();   // = categories.key
            $table->string('key');                   // key kolom (dipakai di pipelines.progress)
            $table->string('name');
            $table->string('color')->default('bg-slate-400'); // dot warna header
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['board_key', 'key']);
        });

        // kolom default (setara PROGRESS lama) untuk tiap board
        $defaults = [
            ['key' => 'script', 'name' => 'Script', 'color' => 'bg-purple-500'],
            ['key' => 'editing', 'name' => 'Editing', 'color' => 'bg-sky-500'],
            ['key' => 'progress', 'name' => 'Progress', 'color' => 'bg-brand-600'],
            ['key' => 'pending', 'name' => 'Pending', 'color' => 'bg-amber-500'],
            ['key' => 'done', 'name' => 'Done', 'color' => 'bg-emerald-500'],
        ];
        $now = now();
        foreach (DB::table('categories')->pluck('key') as $boardKey) {
            foreach ($defaults as $i => $c) {
                DB::table('board_columns')->insert([
                    'board_key' => $boardKey, 'key' => $c['key'], 'name' => $c['name'],
                    'color' => $c['color'], 'position' => $i, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // lepas enum progress → string, tambah labels
        Schema::table('pipelines', function (Blueprint $table) {
            $table->string('progress')->default('script')->change();
            $table->json('labels')->nullable()->after('todos'); // [{name, color}]
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_columns');
        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropColumn('labels');
        });
    }
};
