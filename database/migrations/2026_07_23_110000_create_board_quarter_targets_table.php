<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Target KPI operasional per board per kuartal: "berapa kartu harus selesai".
 *
 *  Sengaja tabel sendiri, bukan kolom di `categories`: target berulang tiap
 *  kuartal dan riwayatnya harus tersimpan. Kolom di board hanya menyimpan
 *  angka yang berlaku sekarang, lalu tertimpa begitu kuartal berganti — dan
 *  perbandingan antar-kuartal jadi mustahil.
 *
 *  `board_key` menunjuk `categories.key` (bukan id) supaya seragam dgn
 *  `pipelines.category` yang juga memakai key. TANPA foreign key, mengikuti
 *  relasi longgar yang sudah dipakai `pipelines.category` — board dihapus
 *  dijaga di BoardController (tak boleh hapus board berisi kartu), bukan di
 *  tingkat skema.
 *
 *  Unik per (board, tahun, kuartal): satu board tak boleh punya dua target
 *  untuk kuartal yang sama — kalau boleh, mana yang dipakai jadi tebakan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_quarter_targets', function (Blueprint $table) {
            $table->id();
            $table->string('board_key')->index();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter');          // 1..4, divalidasi di controller
            $table->unsignedInteger('target_done')->default(0);   // jumlah kartu yang harus selesai
            $table->text('note')->nullable();                // konteks target, opsional
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['board_key', 'year', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_quarter_targets');
    }
};
