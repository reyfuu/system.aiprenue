<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Naskah Reels/TikTok. Diisi agen Daily Script Rave (repo privat, jalan di
 *  GitHub Actions) lewat POST /api/scripts, atau ditulis manual. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scripts')) {
            return;   // skema server pernah menyimpang dari catatan migrasi (impor .sql)
        }

        Schema::create('scripts', function (Blueprint $table) {
            $table->id();
            $table->string('brand', 30);               // raveloux | rave_tailor | fk
            $table->string('title');
            $table->longText('body');                  // naskah utuh; bisa panjang
            $table->date('generated_for');             // tanggal WIB paket ini dibuat
            $table->timestamps();

            // Satu paket = brand + tanggal. Agen mengirim ulang paket yang sama saat
            // workflow di-rerun, jadi index ini yang dipakai membuang paket lama.
            $table->index(['brand', 'generated_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scripts');
    }
};
