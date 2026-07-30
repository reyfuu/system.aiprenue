<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Hak akses menu per peran — pindah dari konstanta kode ke DB supaya bisa
 *  diatur lewat halaman Manajemen Akses tanpa deploy ulang.
 *
 *  Baris ADA = boleh lihat. Tak ada baris = tak boleh. Sengaja begitu (bukan
 *  kolom boolean): mencabut akses = hapus baris, jadi tabelnya selalu ringkas
 *  & tak ada keadaan "ada tapi false" yang membingungkan.
 *
 *  `User::MENU_ACCESS` TIDAK dihapus — tetap jadi nilai awal di sini sekaligus
 *  jaring pengaman kalau tabel ini kosong (lihat User::canSee).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('role_menu_access')) {
            return;   // skema server pernah menyimpang dari catatan migrasi
        }

        Schema::create('role_menu_access', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->string('menu');
            $table->timestamps();
            $table->unique(['role', 'menu']);   // satu baris per pasangan
        });

        // Isi awal = aturan yang selama ini berlaku di kode, supaya hak akses
        // orang TIDAK berubah sedikit pun sesudah migrasi ini jalan.
        $now = now();
        $baris = [];
        foreach (array_keys(User::ROLES) as $role) {
            $izin = User::MENU_ACCESS[$role] ?? [];
            // '*' dibentangkan jadi daftar menu sesungguhnya: halaman centang
            // butuh nilai konkret, dan '*' tak bisa dicentang sebagian.
            $menus = in_array('*', $izin, true) ? array_keys(User::MENUS) : $izin;
            foreach ($menus as $menu) {
                $baris[] = ['role' => $role, 'menu' => $menu, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('role_menu_access')->insert($baris);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu_access');
    }
};
