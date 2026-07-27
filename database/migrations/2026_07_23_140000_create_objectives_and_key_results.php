<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OKR jadi dua tingkat: Objective (kalimat tujuan) berisi Key Result (terukur).
 *
 *  Bentuk lama (`okrs`) memaksa tepat tiga metrik datar per kuartal, sehingga
 *  target yang tak punya sumber data otomatis — "10 klien coaching baru",
 *  "5 kolaborasi kreator" — tak bisa ditulis sama sekali. Itu bukan
 *  keterbatasan kecil: justru target semacam itu yang paling sering muncul
 *  saat menyusun OKR.
 *
 *  `source`/`metric`/`unit` string biasa, bukan enum — mengikuti keputusan yang
 *  sama pada `progress`/`category` (CLAUDE.md): enum MySQL menuntut migrasi
 *  ubah kolom tiap kali daftarnya bertambah. Daftar sahnya ada di konstanta
 *  model & ditegakkan lewat validasi controller.
 *
 *  `objective_id` cascadeOnDelete — SENGAJA berbeda dari `created_by` yang
 *  nullOnDelete. Key Result tanpa Objective tak punya arti apa pun; nama
 *  pembuat yang hilang masih punya.
 *
 *  Baris `okrs` lama dikonversi, bukan dibuang: tiap (tahun, kuartal) jadi satu
 *  Objective, tiap metriknya jadi satu Key Result `auto`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objectives', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter');          // 1..4, divalidasi di controller
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0); // urutan tampil
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['year', 'quarter']);
        });

        Schema::create('key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objective_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('source')->default('manual');     // auto | manual
            $table->string('metric')->nullable();            // view | subscriber | omset (hanya bila auto)
            $table->decimal('target', 20, 2)->default(0);
            // Realisasi KR manual. KR `auto` SELALU null di sini — angkanya
            // dihitung dari Insight/Pembukuan saat dibaca, dan menyimpan
            // salinannya berarti ia bisa basi diam-diam.
            $table->decimal('actual_manual', 20, 2)->nullable();
            $table->string('unit')->default('angka');        // angka | rupiah | persen
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->konversiOkrLama();

        Schema::dropIfExists('okrs');
    }

    /** Pindahkan isi tabel `okrs` ke bentuk dua tingkat. */
    private function konversiOkrLama(): void
    {
        if (! Schema::hasTable('okrs')) {
            return;
        }

        $lama = DB::table('okrs')->orderBy('year')->orderBy('quarter')->get();

        foreach ($lama->groupBy(fn ($r) => $r->year.'-'.$r->quarter) as $baris) {
            $pertama = $baris->first();

            // Judul Objective diambil dari catatan pertama yang terisi — itu
            // satu-satunya teks bebas yang ada di bentuk lama. Kalau semuanya
            // kosong, pakai penamaan netral daripada mengarang tujuan.
            // first(callable), bukan firstWhere(): firstWhere menerima
            // (key, operator, value) dan akan salah tafsir kalau dioper closure.
            $judul = $baris->first(fn ($r) => filled($r->note))?->note
                ?? 'OKR Q'.$pertama->quarter.' '.$pertama->year;

            $objectiveId = DB::table('objectives')->insertGetId([
                'year' => $pertama->year,
                'quarter' => $pertama->quarter,
                'title' => mb_substr($judul, 0, 255),
                'position' => 0,
                'created_by' => $pertama->created_by,
                'created_at' => $pertama->created_at ?? now(),
                'updated_at' => now(),
            ]);

            foreach ($baris->values() as $i => $r) {
                DB::table('key_results')->insert([
                    'objective_id' => $objectiveId,
                    'title' => ucfirst($r->metric),
                    'source' => 'auto',
                    'metric' => $r->metric,
                    'target' => $r->target,
                    'actual_manual' => null,
                    'unit' => $r->metric === 'omset' ? 'rupiah' : 'angka',
                    'position' => $i,
                    'created_by' => $r->created_by,
                    'created_at' => $r->created_at ?? now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Bangun ulang `okrs`, lalu kembalikan KR bertipe auto ke dalamnya.
        // KR manual TIDAK punya tempat di bentuk lama & memang hilang — itu
        // konsekuensi yang tak terhindarkan saat mundur ke skema yang lebih
        // sempit, bukan kelalaian.
        if (! Schema::hasTable('okrs')) {
            Schema::create('okrs', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('quarter');
                $table->string('metric');
                $table->decimal('target', 20, 2)->default(0);
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['year', 'quarter', 'metric']);
            });

            $rows = DB::table('key_results as kr')
                ->join('objectives as o', 'o.id', '=', 'kr.objective_id')
                ->where('kr.source', 'auto')->whereNotNull('kr.metric')
                ->select('o.year', 'o.quarter', 'o.title', 'kr.metric', 'kr.target', 'kr.created_by')
                ->get();

            foreach ($rows as $r) {
                DB::table('okrs')->insertOrIgnore([
                    'year' => $r->year, 'quarter' => $r->quarter, 'metric' => $r->metric,
                    'target' => $r->target, 'note' => $r->title, 'created_by' => $r->created_by,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::dropIfExists('key_results');
        Schema::dropIfExists('objectives');
    }
};
