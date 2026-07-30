<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot harian tingkat AKUN — pertumbuhan, bukan performa konten.
 *
 * Tabel terpisah dari `insight_contents` karena pertanyaannya beda dan tak bisa
 * saling diturunkan: jumlah follower hari ini TIDAK bisa dihitung dari
 * penjumlahan follower_gained tiap konten (ada unfollow, ada pertumbuhan dari
 * luar konten). Growth harus diukur langsung, per hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insight_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('platform');                  // 'instagram' | 'youtube'
            $table->string('akun');                      // ig user id / channel id (UC…)
            $table->string('nama_akun')->nullable();     // label tampil, mis. @freddiekashawan
            $table->date('tanggal');                     // tanggal DATA, bukan tanggal penarikan

            $table->unsignedBigInteger('followers')->nullable();      // followers / subscribers
            $table->unsignedBigInteger('media_count')->nullable();    // jumlah post / video
            $table->unsignedBigInteger('reach')->nullable();          // IG
            $table->unsignedBigInteger('impressions')->nullable();    // IG
            $table->unsignedBigInteger('profile_views')->nullable();  // IG — sinyal konversi
            $table->unsignedBigInteger('link_clicks')->nullable();    // IG, kalau tersedia

            $table->timestamps();

            // Satu baris per akun per hari. Cron boleh jalan dua kali tanpa merusak.
            $table->unique(['platform', 'akun', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insight_accounts');
    }
};
