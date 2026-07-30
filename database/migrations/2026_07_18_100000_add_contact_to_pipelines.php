<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kontak lead di kartu sales: WA, Gmail, DM Instagram.
 *
 *  Tiga kolom terpisah, bukan satu field + dropdown saluran: satu lead lazim
 *  punya lebih dari satu saluran (WA utk chat, Gmail utk invoice, IG utk awal
 *  kenalan) & butuh disimpan sekaligus. Semua nullable — kartu lama tak punya
 *  kontak, dan tak semua lead memberi ketiganya.
 *
 *  Idempoten per kolom: skema server pernah menyimpang dari catatan migrasi,
 *  jadi tiap kolom dicek sendiri (bukan sekali di awal) supaya migrasi yang
 *  separuh tercatat di prod tetap bisa melengkapi sisanya. */
return new class extends Migration
{
    private const COLUMNS = ['kontak_wa', 'kontak_gmail', 'kontak_ig'];

    public function up(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            $after = 'link';
            foreach (self::COLUMNS as $col) {
                if (! Schema::hasColumn('pipelines', $col)) {
                    $table->string($col)->nullable()->after($after);
                }
                $after = $col;
            }
        });
    }

    public function down(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            foreach (self::COLUMNS as $col) {
                if (Schema::hasColumn('pipelines', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
