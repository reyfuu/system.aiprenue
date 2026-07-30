<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metrik per KONTEN — tabel utama menu Insight.
 *
 * Bentuknya sengaja "unified": satu tabel untuk Instagram & YouTube, kolomnya
 * netral platform. Itu syarat supaya keduanya bisa diperingkat berdampingan
 * ("konten mana yang menang?"). Kalau dipisah dua tabel per platform, setiap
 * perbandingan lintas platform jadi UNION manual yang gampang salah.
 *
 * Metrik yang tak berlaku di satu platform dibiarkan NULL — bukan 0. `0` berarti
 * "diukur, hasilnya nol"; NULL berarti "platform ini memang tak punya angka itu".
 * Membedakan keduanya penting waktu menghitung rata-rata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insight_contents', function (Blueprint $table) {
            $table->id();

            $table->string('platform');                 // 'instagram' | 'youtube'
            $table->string('content_id');               // media_id / video_id dari platform
            $table->text('judul')->nullable();          // title (YT) / caption (IG)
            $table->string('url')->nullable();          // permalink
            $table->string('content_type')->nullable(); // reel, feed, short, video
            $table->timestamp('published_at')->nullable();

            // --- Jangkauan ---
            $table->unsignedBigInteger('views')->nullable();        // plays (IG) / views (YT)
            $table->unsignedBigInteger('reach')->nullable();        // IG
            $table->unsignedBigInteger('impressions')->nullable();  // IG (fallback reach)

            // --- Interaksi ---
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->unsignedBigInteger('saves')->nullable();        // IG saja

            // --- Kualitas konsumsi (YouTube) ---
            $table->unsignedBigInteger('watch_time_seconds')->nullable();
            $table->unsignedInteger('avg_view_duration_seconds')->nullable();
            $table->decimal('avg_view_percentage', 5, 2)->nullable();

            // --- Konversi ---
            $table->integer('followers_gained')->nullable();   // signed: YT bisa negatif (lost > gained)

            $table->timestamps();

            // Idempotensi: agen cron menarik ulang konten yang sama tiap hari untuk
            // memperbarui angkanya. Tanpa kunci ini, satu konten beranak tiap
            // penarikan dan peringkatnya jadi ngawur.
            $table->unique(['platform', 'content_id']);

            // Peringkat & grafik hampir selalu difilter per platform lalu diurutkan
            // waktu terbit.
            $table->index(['platform', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insight_contents');
    }
};
