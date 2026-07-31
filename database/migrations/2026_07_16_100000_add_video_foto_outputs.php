<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Tambah pilihan Output 'Video' & 'Foto' (checkbox di modal kartu Sales/Kanban).
 *  Lewat migrasi, bukan seeder: seeder dipagari supaya tak jalan di produksi.
 *
 *  Sengaja pakai DB::table(), BUKAN Model Output — migrasi lain (menambah
 *  SoftDeletes) berjalan SESUDAH ini dalam riwayat, jadi memakai Eloquent di
 *  sini akan menabrak kolom `deleted_at` yang belum ada saat migrasi ini
 *  dieksekusi ulang lewat `migrate:fresh`. Migrasi tak boleh bergantung pada
 *  bentuk model masa depan. */
return new class extends Migration
{
    private const OUTPUTS = ['Video', 'Foto'];

    public function up(): void
    {
        $now = now();
        foreach (self::OUTPUTS as $name) {
            // idempoten: aman kalau sudah ada
            if (! DB::table('outputs')->where('name', $name)->exists()) {
                DB::table('outputs')->insert(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        // Cuma buang output yang belum dipakai kartu mana pun — kalau sudah terpakai,
        // menghapusnya ikut memutus relasi di tabel pivot & mengubah data kartu.
        foreach (self::OUTPUTS as $name) {
            $output = DB::table('outputs')->where('name', $name)->first();
            if ($output && ! DB::table('output_pipeline')->where('output_id', $output->id)->exists()) {
                DB::table('outputs')->where('id', $output->id)->delete();
            }
        }
    }
};
