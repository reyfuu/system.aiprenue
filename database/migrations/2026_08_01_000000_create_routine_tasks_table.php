<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            // "selesai hari ini" = completed_on == hari ini. Tanpa cron: besok
            // tanggalnya beda, jadi centang otomatis kosong lagi tiap pagi.
            // Riwayat sengaja tak disimpan (YAGNI); tambah tabel completions
            // hanya bila nanti perlu streak/analitik.
            $table->date('completed_on')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_tasks');
    }
};
