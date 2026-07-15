<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fitur kartu Kanban: deadline, deskripsi, arsip, komentar, attachment.
return new class extends Migration
{
    public function up(): void
    {
        // Kolom baru di pipelines
        Schema::table('pipelines', function (Blueprint $table) {
            $table->date('deadline')->nullable()->after('tanggal_payment');      // tenggat kartu
            $table->text('description')->nullable()->after('endorse');            // deskripsi task
            $table->timestamp('archived_at')->nullable()->after('updated_at');    // waktu diarsip (null = aktif)
        });

        // Komentar per kartu (siapa pun yg bisa lihat kanban boleh komentar)
        Schema::create('pipeline_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();   // kartu induk
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();       // penulis
            $table->text('body');                                                 // isi komentar
            $table->timestamps();
        });

        // Lampiran file per kartu
        Schema::create('pipeline_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();   // kartu induk
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // pengunggah
            $table->string('path');                                               // path di disk public
            $table->string('name');                                               // nama asli file
            $table->string('mime')->nullable();                                   // tipe mime
            $table->unsignedBigInteger('size')->default(0);                       // ukuran byte
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_attachments');
        Schema::dropIfExists('pipeline_comments');
        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropColumn(['deadline', 'description', 'archived_at']);
        });
    }
};
