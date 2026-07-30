<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Tabel kalender produksi Content, mengikuti kolom spreadsheet operasional. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->string('comp')->nullable();
            $table->string('jenis_postingan')->nullable();
            $table->string('kategori')->nullable();
            $table->text('referensi')->nullable();
            $table->text('inti_pesan')->nullable();
            $table->text('hook_material')->nullable();
            $table->longText('brief_original')->nullable();
            $table->longText('opsi_brief')->nullable();
            $table->longText('script_remake')->nullable();
            $table->string('editor')->nullable();
            $table->string('progress')->default('draft');
            $table->date('tanggal_upload')->nullable()->index();
            $table->text('link_hasil_editing')->nullable();
            $table->text('link_b_roll')->nullable();
            $table->longText('caption')->nullable();
            $table->text('link_ai_kata_kunci')->nullable();
            $table->timestamps();
        });

        // Menu baru langsung tersedia bagi tim pengelola; staff tetap tanpa akses.
        if (Schema::hasTable('role_menu_access')) {
            $now = now();
            DB::table('role_menu_access')->insertOrIgnore(array_map(fn ($role) => [
                'role' => $role,
                'menu' => 'content',
                'created_at' => $now,
                'updated_at' => $now,
            ], ['owner', 'it', 'manager', 'admin']));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_menu_access')) {
            DB::table('role_menu_access')->where('menu', 'content')->delete();
        }

        Schema::dropIfExists('contents');
    }
};
