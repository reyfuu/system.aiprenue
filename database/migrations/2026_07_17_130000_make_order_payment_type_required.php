<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Tipe pembayaran wajib lagi — mengembalikan `120000_make_order_payment_type_optional`.
 *
 *  Migrasi 120000 SENGAJA tidak dihapus meski umurnya cuma sehari: ia sudah
 *  ter-merge & mungkin sudah jalan di produksi. Menghapus berkas migrasi yang
 *  sudah jalan membuat kolomnya nullable selamanya tanpa jejak — server tak
 *  akan pernah tahu harus mengembalikannya. Pasangan naik-turun begini lebih
 *  jujur dibaca sebagai riwayat.
 *
 *  Urutan di up() menentukan hidup-matinya deploy:
 *  isi dulu baris NULL, BARU kunci kolomnya. Terbalik = migrasi mati di tengah
 *  deploy dgn "Column 'tipe_pembayaran' cannot be null", dan itu persis jenis
 *  kegagalan yang pernah menimpa kolom `email` di tabel ini. */
return new class extends Migration
{
    public function up(): void
    {
        // Skema server pernah menyimpang dari catatan migrasi (tabelnya datang
        // dari impor .sql), jadi jangan pernah menganggap kolomnya pasti ada.
        if (! Schema::hasColumn('orders', 'tipe_pembayaran')) {
            return;
        }

        // 1. Baris lama yang terlanjur kosong diisi dulu. 'full' dipilih karena
        //    itu default kolom ini sejak awal (create_orders_table) — bukan
        //    tebakan baru.
        DB::table('orders')->whereNull('tipe_pembayaran')->update(['tipe_pembayaran' => 'full']);

        // 2. Baru dikunci. default('full') DIPERTAHANKAN, bukan sisa warisan:
        //    ada penulis yang sah tak mengirim kolom ini sama sekali (seeder,
        //    Order::create() di tes). Tanpa default, mereka kena NOT NULL
        //    violation = 500. Gerbang "wajib diisi" ditegakkan di validasi
        //    request, tempat kesalahannya bisa jadi pesan form, bukan di SQL.
        Schema::table('orders', fn (Blueprint $table) => $table->string('tipe_pembayaran')->default('full')->change());
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'tipe_pembayaran')) {
            Schema::table('orders', fn (Blueprint $table) => $table->string('tipe_pembayaran')->nullable()->change());
        }
    }
};
