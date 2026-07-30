<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Definisi label kartu yang bisa dikelola owner (dulu hardcoded di Kanban.vue).
 *
 * Kartu tetap menyimpan snapshot {name,color} sendiri di kolom JSON `pipelines.labels`
 * — tabel ini cuma daftar pilihan untuk picker. Menghapus label di sini TIDAK
 * mengubah kartu yang sudah memakainya (by design).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('color', 40);   // kelas Tailwind, WAJIB dari safelist app.css
            $table->timestamps();
        });

        // Seed 5 preset lama supaya perilaku label tak berubah sesudah migrasi.
        $now = now();
        DB::table('labels')->insert([
            ['name' => 'Urgent',  'color' => 'bg-red-500',     'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Penting', 'color' => 'bg-amber-500',   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Review',  'color' => 'bg-purple-500',  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Selesai', 'color' => 'bg-emerald-500', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Info',    'color' => 'bg-sky-500',     'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('labels');
    }
};
