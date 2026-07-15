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
     *  di-rename jadi `total_idr` supaya namanya jujur; tak ada data hilang.
     *
     *  SEMUA langkah dicek dulu, tak ada yang diasumsikan. Alasannya nyata:
     *  tabel `orders` di produksi lahir dari impor .sql, bukan dari migrasi —
     *  tabel `migrations` bilang semua sudah jalan padahal skemanya datang dari
     *  dump, jadi index bawaan (`orders_prioritas_index`) bisa saja tak pernah ada.
     *  Versi pertama migrasi ini menganggap skema server = skema dev, dan pecah
     *  dgn "1091 Can't DROP INDEX". Bonus: jadi idempoten & aman diulang.
     */
    public function up(): void
    {
        // Index bisa beda nama (atau tak ada) di server → cari, jangan tebak namanya.
        $this->dropIndexesOn('orders', 'prioritas');

        if (Schema::hasColumn('orders', 'prioritas')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('prioritas'));
        }

        if (Schema::hasColumn('orders', 'total_pembayaran') && ! Schema::hasColumn('orders', 'total_idr')) {
            Schema::table('orders', fn (Blueprint $table) => $table->renameColumn('total_pembayaran', 'total_idr'));
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'total_usd')) {
                $table->decimal('total_usd', 15, 2)->default(0)->after('total_idr');
            }
            if (! Schema::hasColumn('orders', 'email')) {
                $table->string('email')->nullable()->after('telepon');
            }
            if (! Schema::hasColumn('orders', 'account')) {
                // fk | ai_preneur — order ini masuk akun yang mana
                $table->string('account')->default('fk')->index()->after('tipe_order');
            }
            if (! Schema::hasColumn('orders', 'invoice')) {
                // invoice dari perusahaan (path disk 'public') — terpisah dari bukti_bayar customer
                $table->string('invoice')->nullable()->after('bukti_bayar');
            }
        });

        // Selaras dgn Pipeline::JENIS — order lama tak menyimpan pembedanya,
        // jadi semua ke 1on1 lalu di-retag manual.
        DB::table('orders')->where('tipe_order', 'coaching')->update(['tipe_order' => 'coaching_1on1']);
    }

    public function down(): void
    {
        DB::table('orders')->whereIn('tipe_order', ['coaching_1on1', 'coaching_perusahaan'])
            ->update(['tipe_order' => 'coaching']);

        $this->dropIndexesOn('orders', 'account');

        Schema::table('orders', function (Blueprint $table) {
            foreach (['total_usd', 'email', 'account', 'invoice'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasColumn('orders', 'total_idr') && ! Schema::hasColumn('orders', 'total_pembayaran')) {
            Schema::table('orders', fn (Blueprint $table) => $table->renameColumn('total_idr', 'total_pembayaran'));
        }

        if (! Schema::hasColumn('orders', 'prioritas')) {
            Schema::table('orders', fn (Blueprint $table) => $table->string('prioritas')->nullable()->index());
        }
    }

    /** Buang semua index yang memakai $column, apa pun namanya.
     *  Schema::getIndexes() lintas-driver — jalan di MySQL & SQLite (test). */
    private function dropIndexesOn(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (in_array($column, $index['columns'], true) && ! $index['primary']) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index['name']));
            }
        }
    }
};
