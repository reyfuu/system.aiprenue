<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat notifikasi aplikasi yang disimpan di server.
 *
 * Browser Notification saja tidak cukup untuk penugasan: izin bisa ditolak,
 * tab bisa sedang tertutup, dan tidak ada riwayat yang dapat dibaca ulang.
 * Tabel bawaan Laravel ini menyimpan notifikasi per user; pengirimannya tetap
 * sinkron sehingga tidak membutuhkan queue worker tambahan di server.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Aman bila migrasi pernah dibuat manual di server sebelum file ini
        // ikut dideploy.
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
