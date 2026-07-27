<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OkrObjective extends Model
{
    protected $fillable = ['title', 'period', 'owner', 'deadline', 'description', 'board_key'];

    protected $casts = [
        'deadline' => 'date',
    ];

    /** Key result milik objective ini. cascadeOnDelete di migrasi → hapus
     *  objective ikut membuang KR-nya, jadi tak perlu dihapus manual. */
    public function keyResults(): HasMany
    {
        return $this->hasMany(OkrKeyResult::class, 'objective_id');
    }

    /** Sinkron SATU ARAH (OKR → Kanban): 1 objective = 1 board, tiap key result
     *  = 1 kartu. Dipanggil OkrController (store/update) & seeder.
     *
     *  Aman dari kehilangan data & idempoten (dicocokkan lewat JUDUL kartu):
     *   - Board dibuat sekali (kolom To Do/Dikerjakan/Selesai), lalu di-rename saja.
     *   - KR baru → kartu baru; kartu yg sudah ada TAK PERNAH dihapus (kerjaan
     *     Kanban manual tetap utuh). KR yg dihapus/di-rename meninggalkan kartu
     *     lama sbg yatim — sengaja, biar tak menghapus kerja orang.
     *   - KR selesai → kartu dipindah ke kolom 'done' + done=true. KR belum →
     *     done=false, kolomnya DIBIARKAN (hormati posisi yg digeser manual). */
    public function syncKanbanBoard(): void
    {
        $this->loadMissing('keyResults');

        DB::transaction(function () {
            $key = $this->ensureBoard();

            foreach ($this->keyResults as $kr) {
                $card = Pipeline::where('category', $key)->where('endorse', $kr->title)->first();

                if (! $card) {
                    Pipeline::create([
                        'category' => $key,
                        'endorse'  => $kr->title,
                        'progress' => $kr->completed ? 'done' : 'todo',
                        'done'     => $kr->completed,
                    ]);
                    continue;
                }

                if ($kr->completed) {
                    $card->update(['done' => true, 'progress' => 'done']); // selesai → pindah ke Selesai
                } elseif ($card->done) {
                    $card->update(['done' => false]); // batal selesai; jangan geser kolom
                }
            }
        });
    }

    /** Pastikan board Kanban-nya ada; kembalikan key-nya. Buat + kolom default
     *  bila belum ada (atau board_key menunjuk board yg sudah terhapus). */
    private function ensureBoard(): string
    {
        if ($this->board_key && Category::where('key', $this->board_key)->exists()) {
            Category::where('key', $this->board_key)->update(['name' => $this->title]); // rename ikut judul
            return $this->board_key;
        }

        $key = $this->uniqueBoardKey($this->title);
        Category::create(['key' => $key, 'name' => $this->title, 'type' => 'kanban', 'section' => 'OKR']);

        // Alur task sederhana ala Trello (sama dgn BoardController::store).
        foreach ([
            ['key' => 'todo', 'name' => 'To Do', 'color' => 'bg-slate-400'],
            ['key' => 'progress', 'name' => 'Dikerjakan', 'color' => 'bg-sky-500'],
            ['key' => 'done', 'name' => 'Selesai', 'color' => 'bg-emerald-500'],
        ] as $i => $col) {
            BoardColumn::create(['board_key' => $key, 'key' => $col['key'], 'name' => $col['name'], 'color' => $col['color'], 'position' => $i]);
        }

        $this->update(['board_key' => $key]); // simpan tautan supaya sync berikutnya pakai board yg sama
        return $key;
    }

    /** Slug unik utk key board (anti-tabrakan dgn board lain). */
    private function uniqueBoardKey(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'okr';
        $key = $base;
        $i = 2;
        while (Category::where('key', $key)->exists()) {
            $key = $base.'_'.$i++;
        }

        return $key;
    }
}
