<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Penanggung jawab per Key Result.
 *
 *  Terpisah dari `created_by`: yang menuliskan target dan yang mengejarnya
 *  sering bukan orang yang sama — persis alasan `assigned_to` dipisah dari
 *  `created_by` di tabel pipelines.
 *
 *  Untuk sekarang semuanya diisi owner (sesuai keputusan "sementara
 *  penanggung jawabnya owner dulu"), termasuk baris yang sudah ada. Kolomnya
 *  tetap FK ke users supaya pemilih PJ nanti tinggal dipasang di form tanpa
 *  migrasi lagi.
 *
 *  nullOnDelete, bukan cascade: owner yang dihapus tak boleh ikut menghapus
 *  Key Result-nya — yang hilang cukup namanya. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('key_results', function (Blueprint $table) {
            if (! Schema::hasColumn('key_results', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->after('unit')
                    ->constrained('users')->nullOnDelete();
            }
        });

        // Isi baris yang sudah ada. Kalau belum ada user berperan owner
        // (mis. basis data kosong di CI), biarkan null — jangan menunjuk
        // orang yang kebetulan ber-id 1.
        if ($ownerId = DB::table('users')->where('role', 'owner')->orderBy('id')->value('id')) {
            DB::table('key_results')->whereNull('owner_id')->update(['owner_id' => $ownerId]);
        }
    }

    public function down(): void
    {
        Schema::table('key_results', function (Blueprint $table) {
            if (Schema::hasColumn('key_results', 'owner_id')) {
                $table->dropConstrainedForeignId('owner_id');
            }
        });
    }
};
