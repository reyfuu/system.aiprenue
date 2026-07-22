<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Absensi: pengajuan cuti/sakit/izin per user, dgn lampiran keterangan opsional
 *  (mis. surat dokter untuk sakit). Menu 'absensi' sengaja terbuka untuk semua
 *  peran — gerbangnya di User::canSee(), bukan role_menu_access. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');                       // cuti | sakit | izin
            $table->date('start_date');
            $table->date('end_date')->nullable();         // null = satu hari (start_date)
            $table->text('reason')->nullable();           // keterangan/alasan
            $table->string('attachment_path')->nullable();// surat keterangan (opsional)
            $table->string('status')->default('menunggu');// menunggu | disetujui | ditolak
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absences');
    }
};
