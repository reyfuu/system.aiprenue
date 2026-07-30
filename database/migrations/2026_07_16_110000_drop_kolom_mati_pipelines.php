<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Buang kolom `pipelines` yang tak lagi dipakai UI mana pun:
 *  - ke_gilang  : tak pernah ada field-nya di form; isinya cuma bikinan seeder.
 *  - catatan    : duplikat `notes` (yang dipakai form); selalu kosong.
 *  - created_by : tak pernah diisi — tak ada kode yang menulisnya.
 *  - updated_by : idem.
 *
 *  Semua bergaya "buang kalau ada": skema server pernah menyimpang dari catatan
 *  migrasi (tabelnya datang dari impor .sql), jadi jangan pernah asumsikan bentuknya. */
return new class extends Migration
{
    private const KOLOM = ['ke_gilang', 'catatan', 'created_by', 'updated_by'];

    /** MySQL (dev & produksi) vs SQLite (tes) beda perlakuan soal foreign key. */
    private function sqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }

    public function up(): void
    {
        $buang = array_values(array_filter(self::KOLOM, fn ($k) => Schema::hasColumn('pipelines', $k)));
        if (! $buang) {
            return;
        }
        // created_by/updated_by menunjuk users — FK-nya harus lepas dulu, dua driver
        // dua cara. Salah satunya saja = separuh lingkungan pecah.
        $berFk = array_values(array_intersect(['created_by', 'updated_by'], $buang));

        if ($this->sqlite()) {
            // SQLite: dropForeign WAJIB pakai daftar kolom — versi by-name melempar
            // RuntimeException. Digabung dgn dropColumn dalam satu Blueprint supaya
            // tabelnya dibangun ulang sekali; kalau terpisah, DROP COLUMN bawaan
            // SQLite menolak ("unknown column ... in foreign key definition").
            Schema::table('pipelines', function (Blueprint $t) use ($buang, $berFk) {
                foreach ($berFk as $kolom) {
                    $t->dropForeign([$kolom]);
                }
                $t->dropColumn($buang);
            });

            return;
        }

        // MySQL: lepas FK lebih dulu & TERPISAH — kolom yang masih dipegang FK tak
        // bisa di-drop. Namanya dicari, bukan ditebak (lihat dropForeignKeysOn).
        foreach ($berFk as $kolom) {
            $this->dropForeignKeysOn('pipelines', $kolom);
        }
        Schema::table('pipelines', fn (Blueprint $t) => $t->dropColumn($buang));
    }

    public function down(): void
    {
        // Bentuk kolomnya dikembalikan; isinya tidak — drop memang membuang datanya.
        $sqlite = $this->sqlite();

        Schema::table('pipelines', function (Blueprint $t) use ($sqlite) {
            if (! Schema::hasColumn('pipelines', 'ke_gilang')) {
                $t->enum('ke_gilang', ['belum', 'sudah', 'done'])->default('belum');
            }
            if (! Schema::hasColumn('pipelines', 'catatan')) {
                $t->text('catatan')->nullable();
            }
            foreach (['created_by', 'updated_by'] as $kolom) {
                if (Schema::hasColumn('pipelines', $kolom)) {
                    continue;
                }
                $col = $t->foreignId($kolom)->nullable();
                // SQLite tak bisa menambah FK lewat ALTER — kolomnya saja sudah cukup
                // untuk mengembalikan bentuk tabel.
                if (! $sqlite) {
                    $col->constrained('users')->nullOnDelete();
                }
            }
        });
    }

    /** Cari FK lewat Schema::getForeignKeys() — namanya jangan ditebak: server
     *  hasil impor .sql bisa punya nama FK yang beda dari bawaan Laravel. */
    private function dropForeignKeysOn(string $tabel, string $kolom): void
    {
        if (! Schema::hasColumn($tabel, $kolom)) {
            return;
        }
        foreach (Schema::getForeignKeys($tabel) as $fk) {
            if (in_array($kolom, $fk['columns'], true)) {
                Schema::table($tabel, fn (Blueprint $t) => $t->dropForeign($fk['name']));
            }
        }
    }
};
