<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Judul OKR perusahaan bisa diedit bebas per (tahun, kuartal). Kalau tak ada
// barisnya, halaman OKR pakai judul default "OKR Perusahaan SKINKU {label}".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('okr_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter');
            $table->string('title');
            $table->timestamps();
            $table->softDeletes(); // OkrPeriod pakai SoftDeletes — lihat migrasi add_soft_deletes_to_domain_tables
            $table->unique(['year', 'quarter']); // satu judul per periode
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('okr_periods');
    }
};
