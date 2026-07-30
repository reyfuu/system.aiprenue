<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Samakan bentuk kolom `orders` di server dgn yang dimaui aplikasi.
     *
     *  Kenapa terpisah dari 140000: tabel `orders` di produksi lahir dari impor .sql,
     *  jadi kolom yang "sudah ada" belum tentu bentuknya benar — `email` di sana
     *  NOT NULL, sehingga pagar hasColumn() di 140000 melewatinya dan insert pecah
     *  dgn "1048 Column 'email' cannot be null". 140000 sendiri sudah terlanjur
     *  tercatat sukses di prod, jadi mengeditnya tak akan pernah jalan lagi di sana.
     *
     *  Ditegakkan SEMUA kolom opsional sekaligus, bukan cuma `email`: kalau satu kolom
     *  bisa melenceng diam-diam, yang lain juga — dan tiap kolom yang terlewat berarti
     *  satu ronde error → deploy → error lagi.
     *
     *  Idempoten: di DB yang bentuknya sudah benar, ini no-op.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Semua nullable di DB — TERMASUK yang wajib di form (deadline/telepon/kota).
            // Bukan kelalaian: baris order lama hasil impor .sql banyak yang kosong,
            // dan NOT NULL akan menolak seluruh tabelnya. Wajibnya ditegakkan
            // OrderController@rules, di gerbang aplikasi, tempat data baru masuk.
            $table->date('tanggal_deadline')->nullable()->change();
            $table->string('telepon')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('kota')->nullable()->change();
            $table->text('alamat')->nullable()->change();
            $table->date('tanggal_bayar')->nullable()->change();
            $table->string('bukti_bayar')->nullable()->change();
            $table->string('invoice')->nullable()->change();

            // Wajib, tapi selalu punya default → aman NOT NULL
            $table->string('tipe_order')->default('endorse')->change();
            $table->string('account')->default('fk')->change();
            $table->string('tipe_pembayaran')->default('full')->change();
            $table->decimal('total_idr', 15, 2)->default(0)->change();
            $table->decimal('total_usd', 15, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        // Sengaja kosong: ini perbaikan penyimpangan skema, bukan perubahan fitur.
        // Tak ada bentuk lama yang layak dikembalikan — bentuk lamanya justru yang rusak.
    }
};
