<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // --- Info Order ---
            // string (bukan enum) agar pilihan bisa ditambah tanpa migrasi ubah kolom
            $table->string('tipe_order')->default('endorse')->index();
            $table->string('prioritas')->nullable()->index();   // nullable: "Pilih Prioritas" = belum diisi
            $table->date('tanggal_deadline')->nullable()->index();

            // --- Customer ---
            $table->string('nama_customer');
            $table->string('telepon')->nullable();
            $table->string('kota')->nullable();                 // dari config/wilayah.php
            $table->text('alamat')->nullable();

            // --- Pembayaran ---
            $table->string('tipe_pembayaran')->default('full'); // full | dp
            $table->date('tanggal_bayar')->nullable();
            $table->string('bukti_bayar')->nullable();          // path di disk 'public'
            $table->decimal('total_pembayaran', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
