<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Order: buang prioritas, pecah tipe coaching, pisah nominal IDR/USD,
     *  tambah email / account / invoice perusahaan.
     *
     *  `total_pembayaran` berhenti jadi angka yang diketik → jadi turunan
     *  (total_idr + total_usd × kurs) yang dihitung saat tampil. Kolomnya
     *  di-rename jadi `total_idr` supaya namanya jujur; tak ada data hilang. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['prioritas']);
            $table->dropColumn('prioritas');
            $table->renameColumn('total_pembayaran', 'total_idr');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_usd', 15, 2)->default(0)->after('total_idr');
            $table->string('email')->nullable()->after('telepon');
            // fk | ai_preneur — order ini masuk akun yang mana
            $table->string('account')->default('fk')->index()->after('tipe_order');
            // invoice dari perusahaan (path disk 'public') — terpisah dari bukti_bayar customer
            $table->string('invoice')->nullable()->after('bukti_bayar');
        });

        // Selaras dgn Pipeline::JENIS — kartu lama tak menyimpan pembedanya,
        // jadi semua ke 1on1 lalu di-retag manual.
        DB::table('orders')->where('tipe_order', 'coaching')->update(['tipe_order' => 'coaching_1on1']);
    }

    public function down(): void
    {
        DB::table('orders')->whereIn('tipe_order', ['coaching_1on1', 'coaching_perusahaan'])
            ->update(['tipe_order' => 'coaching']);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['total_usd', 'email', 'account', 'invoice']);
            $table->renameColumn('total_idr', 'total_pembayaran');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('prioritas')->nullable()->index();
        });
    }
};
