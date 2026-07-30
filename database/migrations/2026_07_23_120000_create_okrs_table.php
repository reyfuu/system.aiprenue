<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** OKR tingkat perusahaan: target per kuartal untuk view, subscriber, omset.
 *
 *  Satu baris = satu key result (satu metrik, satu kuartal). Bukan satu baris
 *  berisi tiga kolom target: metrik bisa bertambah nanti (mis. leads) dan
 *  bentuk baris-per-metrik menambahnya tanpa migrasi kolom. Konsekuensinya
 *  yang harus diterima: kuartal boleh punya sebagian metrik saja, dan itu
 *  memang sah — target yang belum ditetapkan lebih jujur daripada target 0.
 *
 *  `metric` string biasa, bukan enum — mengikuti keputusan yang sama pada
 *  `progress`/`category` di repo ini (lihat docs/CLAUDE.md): enum MySQL menuntut
 *  migrasi ubah kolom tiap kali daftarnya bertambah. Daftar sahnya ada di
 *  Okr::METRICS dan ditegakkan lewat validasi controller.
 *
 *  `target` decimal(20,2): omset dalam rupiah menembus jangkauan integer biasa
 *  begitu bicara miliaran, dan view kanal besar juga bukan angka kecil.
 *
 *  Realisasinya TIDAK disimpan di sini — dihitung saat dibaca dari
 *  insight_contents (view), insight_accounts (subscriber) & transactions
 *  (omset). Menyimpan salinan angka realisasi berarti ia bisa basi diam-diam
 *  saat data sumbernya dikoreksi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('okrs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter');          // 1..4, divalidasi di controller
            $table->string('metric');                        // view | subscriber | omset
            $table->decimal('target', 20, 2)->default(0);
            $table->text('note')->nullable();                // konteks/objective singkat
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Satu metrik hanya boleh punya satu target per kuartal.
            $table->unique(['year', 'quarter', 'metric']);
        });

        // Menu baru langsung tersedia bagi tim pengelola. OKR memuat omset
        // (data keuangan), jadi admin & staff sengaja TIDAK diikutkan —
        // sejalan dgn pembatasan menu pembukuan.
        if (Schema::hasTable('role_menu_access')) {
            $now = now();
            DB::table('role_menu_access')->insertOrIgnore(array_map(fn ($role) => [
                'role' => $role,
                'menu' => 'okr',
                'created_at' => $now,
                'updated_at' => $now,
            ], ['owner', 'it', 'manager']));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_menu_access')) {
            DB::table('role_menu_access')->where('menu', 'okr')->delete();
        }

        Schema::dropIfExists('okrs');
    }
};
