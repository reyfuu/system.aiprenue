<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pindahkan target omzet dari Key Result ke Objective.
 *
 * Omzet adalah target tingkat tujuan, sedangkan Key Result di halaman ini
 * dipakai untuk pekerjaan/hasil pendukungnya. Migrasi juga membawa baris KR
 * omzet yang mungkin sudah dibuat oleh versi sebelumnya, lalu menghapus baris
 * itu setelah nilainya aman tersimpan pada Objective.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('objectives')) {
            return;
        }

        $needsTarget = ! Schema::hasColumn('objectives', 'omset_target');
        $needsOwner = ! Schema::hasColumn('objectives', 'omset_owner_id');

        if ($needsTarget || $needsOwner) {
            Schema::table('objectives', function (Blueprint $table) use ($needsTarget, $needsOwner): void {
                if ($needsTarget) {
                    $table->decimal('omset_target', 20, 2)->nullable()->after('priority');
                }
                if ($needsOwner) {
                    $table->foreignId('omset_owner_id')->nullable()->after('omset_target')
                        ->constrained('users')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('key_results')) {
            return;
        }

        $ownerSelect = Schema::hasColumn('key_results', 'owner_id')
            ? 'MAX(owner_id) as owner_id'
            : 'NULL as owner_id';
        $targets = DB::table('key_results')
            ->where('source', 'auto')
            ->where('metric', 'omset')
            ->selectRaw("objective_id, SUM(target) as target, {$ownerSelect}")
            ->groupBy('objective_id')
            ->get();

        foreach ($targets as $target) {
            DB::table('objectives')->where('id', $target->objective_id)->update([
                'omset_target' => $target->target,
                'omset_owner_id' => $target->owner_id,
                'updated_at' => now(),
            ]);
        }

        DB::table('key_results')
            ->where('source', 'auto')
            ->where('metric', 'omset')
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('objectives')) {
            return;
        }

        if (Schema::hasTable('key_results') && Schema::hasColumn('objectives', 'omset_target')) {
            $objectives = DB::table('objectives')->where('omset_target', '>', 0)->get();

            foreach ($objectives as $objective) {
                DB::table('key_results')->insert([
                    'objective_id' => $objective->id,
                    'title' => 'Omzet kuartal',
                    'source' => 'auto',
                    'metric' => 'omset',
                    'target' => $objective->omset_target,
                    'actual_manual' => null,
                    'unit' => 'rupiah',
                    'priority' => $objective->priority ?? null,
                    'position' => (int) DB::table('key_results')
                        ->where('objective_id', $objective->id)
                        ->max('position') + 1,
                    'owner_id' => $objective->omset_owner_id ?? null,
                    'created_by' => $objective->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasColumn('objectives', 'omset_owner_id')) {
            Schema::table('objectives', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('omset_owner_id');
            });
        }
        if (Schema::hasColumn('objectives', 'omset_target')) {
            Schema::table('objectives', function (Blueprint $table): void {
                $table->dropColumn('omset_target');
            });
        }
    }
};
