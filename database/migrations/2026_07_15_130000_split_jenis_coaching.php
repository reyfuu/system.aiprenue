<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** `coaching` dipecah jadi `coaching_1on1` + `coaching_perusahaan`.
     *
     *  Kartu lama tak menyimpan pembedanya, jadi tak ada cara memilah otomatis —
     *  semua diarahkan ke 1on1 lalu di-retag manual lewat UI. Tanpa remap, kartu
     *  lama memakai jenis yg tak ada di Pipeline::JENIS: labelnya jadi key mentah
     *  & validasi `in:` menolaknya begitu kartu diedit. */
    public function up(): void
    {
        DB::table('pipelines')->where('jenis', 'coaching')->update(['jenis' => 'coaching_1on1']);
    }

    public function down(): void
    {
        // coaching_perusahaan ikut dilebur balik — pemisahannya tak ada di skema lama.
        DB::table('pipelines')->whereIn('jenis', ['coaching_1on1', 'coaching_perusahaan'])
            ->update(['jenis' => 'coaching']);
    }
};
