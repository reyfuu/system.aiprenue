<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();  // slug dipakai di pipelines.category
            $table->string('name');
            $table->timestamps();
        });

        // seed kategori bawaan (setara enum lama)
        $now = now();
        DB::table('categories')->insert([
            ['key' => 'endorse', 'name' => 'Endorse', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'agensi', 'name' => 'Agensi', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'coaching', 'name' => 'Coaching', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'speaker', 'name' => 'Speaker', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // lepas constraint enum → string biar kategori baru bisa dipakai
        Schema::table('pipelines', function (Blueprint $table) {
            $table->string('category')->default('endorse')->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
        // biarkan pipelines.category sebagai string (tidak dikembalikan ke enum)
    }
};
