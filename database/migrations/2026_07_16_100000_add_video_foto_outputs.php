<?php

use App\Models\Output;
use Illuminate\Database\Migrations\Migration;

/** Tambah pilihan Output 'Video' & 'Foto' (checkbox di modal kartu Sales/Kanban).
 *  Lewat migrasi, bukan seeder: seeder dipagari supaya tak jalan di produksi. */
return new class extends Migration
{
    private const OUTPUTS = ['Video', 'Foto'];

    public function up(): void
    {
        foreach (self::OUTPUTS as $name) {
            Output::firstOrCreate(['name' => $name]);   // idempoten: aman kalau sudah ada
        }
    }

    public function down(): void
    {
        // Cuma buang output yang belum dipakai kartu mana pun — kalau sudah terpakai,
        // menghapusnya ikut memutus relasi di tabel pivot & mengubah data kartu.
        foreach (self::OUTPUTS as $name) {
            $output = Output::where('name', $name)->first();
            if ($output && $output->pipelines()->doesntExist()) {
                $output->delete();
            }
        }
    }
};
