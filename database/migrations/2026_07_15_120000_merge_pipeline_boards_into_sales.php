<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Satu board sales saja. endorse/coaching/agensi/speaker berhenti jadi board,
     *  turun jadi atribut kartu `jenis` — supaya semua deal berbagi satu papan &
     *  satu corong stage, tapi asal-usulnya tetap kebaca & bisa difilter. */
    private const KEYS = ['endorse', 'coaching', 'agensi', 'speaker'];

    private const STAGES = [
        ['key' => 'lead', 'name' => 'Lead', 'color' => 'bg-slate-400'],
        ['key' => 'kontak', 'name' => 'Kontak', 'color' => 'bg-sky-500'],
        ['key' => 'nego', 'name' => 'Nego', 'color' => 'bg-amber-500'],
        ['key' => 'closing', 'name' => 'Closing', 'color' => 'bg-brand-600'],
        ['key' => 'deal', 'name' => 'Deal', 'color' => 'bg-emerald-500'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('pipelines', 'jenis')) {
            Schema::table('pipelines', function (Blueprint $table) {
                $table->string('jenis')->nullable()->after('category'); // null = kartu board kanban
            });
        }

        // Board sales wajib ada sbg tujuan pindahan — kalau hilang, jangan yatimkan kartu.
        if (! DB::table('categories')->where('key', 'sales')->exists()) {
            throw new RuntimeException('Board `sales` tak ada — jalankan migrasi add_sales_pipeline_board dulu.');
        }

        DB::transaction(function () {
            foreach (self::KEYS as $key) {
                // DB::table = lewati scope SoftDeletes → kartu terarsip & terhapus ikut pindah.
                // Stage-nya sudah sales (migrasi 110000), jadi tak perlu remap progress.
                DB::table('pipelines')->where('category', $key)
                    ->update(['category' => 'sales', 'jenis' => $key]);

                DB::table('board_columns')->where('board_key', $key)->delete();
                DB::table('categories')->where('key', $key)->delete();
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $now = now();
            foreach (self::KEYS as $key) {
                DB::table('categories')->insert([
                    'key' => $key, 'name' => ucfirst($key), 'type' => 'pipeline',
                    'section' => null, 'super_admin_only' => false,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                foreach (self::STAGES as $i => $c) {
                    DB::table('board_columns')->insert([
                        'board_key' => $key, 'key' => $c['key'], 'name' => $c['name'],
                        'color' => $c['color'], 'position' => $i,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                DB::table('pipelines')->where('jenis', $key)
                    ->update(['category' => $key, 'jenis' => null]);
            }
        });

        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropColumn('jenis');
        });
    }
};
