<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Stage sales untuk SEMUA board tipe `pipeline` (endorse/coaching/agensi/speaker),
     *  bukan cuma board `sales`. Board tipe `kanban` tak disentuh — kolom produksinya
     *  (script/editing/…) masih dipakai Kanban & statistik Dashboard.
     *
     *  Kartu lama ikut di-remap POSISIONAL: kolom ke-n board lama → stage ke-n.
     *  Sama-sama 5 stage berurutan, jadi 1:1 & bisa dibalik down(). Board dgn kolom
     *  lebih banyak (pernah ditambah manual) → sisanya jatuh ke stage terakhir. */
    private const STAGES = [
        ['key' => 'lead',    'name' => 'Lead',    'color' => 'bg-slate-400'],
        ['key' => 'kontak',  'name' => 'Kontak',  'color' => 'bg-sky-500'],
        ['key' => 'nego',    'name' => 'Nego',    'color' => 'bg-amber-500'],
        ['key' => 'closing', 'name' => 'Closing', 'color' => 'bg-brand-600'],
        ['key' => 'deal',    'name' => 'Deal',    'color' => 'bg-emerald-500'],
    ];

    /** Kolom produksi bawaan — dipakai down() untuk memulihkan board lama. */
    private const LEGACY = [
        ['key' => 'script',   'name' => 'Script',   'color' => 'bg-purple-500'],
        ['key' => 'editing',  'name' => 'Editing',  'color' => 'bg-sky-500'],
        ['key' => 'progress', 'name' => 'Progress', 'color' => 'bg-brand-600'],
        ['key' => 'pending',  'name' => 'Pending',  'color' => 'bg-amber-500'],
        ['key' => 'done',     'name' => 'Done',     'color' => 'bg-emerald-500'],
    ];

    public function up(): void
    {
        $this->retarget(self::STAGES);
    }

    public function down(): void
    {
        $this->retarget(self::LEGACY);
    }

    /** Ganti kolom tiap board pipeline (selain `sales`) ke $target, remap kartu posisional. */
    private function retarget(array $target): void
    {
        $boards = DB::table('categories')->where('type', 'pipeline')->where('key', '!=', 'sales')->pluck('key');

        foreach ($boards as $board) {
            $old = DB::table('board_columns')->where('board_key', $board)->orderBy('position')->pluck('key');
            if ($old->isEmpty() || $old->first() === $target[0]['key']) {
                continue;  // idempoten: sudah pakai skema tujuan
            }

            DB::transaction(function () use ($board, $old, $target) {
                // Remap kartu dulu, selagi kolom lama masih ada.
                // DB::table = lewati scope SoftDeletes → kartu terhapus/terarsip ikut, biar
                // tak jadi progress yatim kalau nanti di-restore.
                foreach ($old as $i => $oldKey) {
                    $newKey = ($target[$i] ?? $target[count($target) - 1])['key'];
                    if ($oldKey === $newKey) {
                        continue;
                    }
                    DB::table('pipelines')->where('category', $board)->where('progress', $oldKey)
                        ->update(['progress' => $newKey]);
                }

                DB::table('board_columns')->where('board_key', $board)->delete();

                $now = now();
                foreach ($target as $i => $c) {
                    DB::table('board_columns')->insert([
                        'board_key' => $board, 'key' => $c['key'], 'name' => $c['name'],
                        'color' => $c['color'], 'position' => $i,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            });
        }
    }
};
