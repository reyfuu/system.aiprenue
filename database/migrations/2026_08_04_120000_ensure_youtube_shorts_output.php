<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OUTPUTS = ['YouTube Shorts'];

    public function up(): void
    {
        $now = now();

        foreach (self::OUTPUTS as $name) {
            $legacyName = 'YouTube Short';
            if ($name === 'YouTube Shorts') {
                $legacy = DB::table('outputs')->where('name', $legacyName)->first();
                $current = DB::table('outputs')->where('name', $name)->first();

                if (! $current && $legacy) {
                    $payload = ['name' => $name, 'updated_at' => $now];
                    if (Schema::hasColumn('outputs', 'deleted_at')) {
                        $payload['deleted_at'] = null;
                    }

                    DB::table('outputs')->where('id', $legacy->id)->update($payload);
                    continue;
                }
            }

            $existing = DB::table('outputs')->where('name', $name)->first();

            if (! $existing) {
                DB::table('outputs')->insert(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
                continue;
            }

            // Kembali munculkan opsi output jika pernah terhapus soft-delete.
            if (Schema::hasColumn('outputs', 'deleted_at') && $existing->deleted_at !== null) {
                DB::table('outputs')->where('id', $existing->id)->update(['deleted_at' => null, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        // Hapus balik opsi ini hanya jika belum dipakai relasi apa pun.
        foreach (self::OUTPUTS as $name) {
            $output = DB::table('outputs')->where('name', $name)->first();
            if (! $output) {
                continue;
            }

            if (DB::table('output_pipeline')->where('output_id', $output->id)->exists()) {
                continue;
            }

            DB::table('outputs')->where('id', $output->id)->delete();
        }
    }
};
