<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Category;
use App\Models\Pipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BoardController extends Controller
{
    /** Board baru SELALU kanban. Sales cuma punya satu board (`sales`) — yang
     *  membedakan deal di sana adalah `jenis`, bukan board terpisah. Ditegakkan di
     *  sini, bukan cuma dgn menyembunyikan tombolnya di Vue: request langsung tetap
     *  tembus kalau gerbangnya cuma di frontend. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'section' => 'nullable|string|max:100',
        ]);

        $key = $this->uniqueKey($data['name']);
        Category::create([
            'key' => $key,
            'name' => trim($data['name']),
            'type' => 'kanban',
            'section' => filled($data['section'] ?? null) ? trim($data['section']) : null,
            // Pembuat board, dari sesi (bukan request) — alasan sama dgn kartu.
            'created_by' => $request->user()?->id,
        ]);

        // Setiap proyek baru langsung punya alur task sederhana ala Trello.
        $defaults = [
            ['key' => 'todo', 'name' => 'To Do', 'color' => 'bg-slate-400'],
            ['key' => 'progress', 'name' => 'Dikerjakan', 'color' => 'bg-sky-500'],
            ['key' => 'done', 'name' => 'Selesai', 'color' => 'bg-emerald-500'],
        ];
        foreach ($defaults as $i => $col) {
            BoardColumn::create([
                'board_key' => $key,
                'key' => $col['key'],
                'name' => $col['name'],
                'color' => $col['color'],
                'position' => $i,
            ]);
        }

        return redirect()->route('pipelines.kanban', ['category' => $key])
            ->with('status', 'Board ditambahkan.');
    }

    public function update(Request $request, Category $board)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'section' => 'nullable|string|max:100',
        ]);
        $board->update([
            'name' => trim($data['name']),
            'section' => filled($data['section'] ?? null) ? trim($data['section']) : null,
        ]);

        // Balik ke modul asal board — board pipeline tak ada di /pipelines/kanban
        $route = $board->type === 'pipeline' ? 'pipelines.index' : 'pipelines.kanban';

        return redirect()->route($route, ['category' => $board->key])
            ->with('status', 'Board diperbarui.');
    }

    public function destroy(Category $board)
    {
        // Todo List adalah board bawaan permanen dan selalu tersedia di Kanban.
        if ($board->key === 'todolist') {
            return back()->with('status', 'Board Todo List bawaan tidak bisa dihapus.');
        }

        // Board sales tak boleh hilang: menu Sales Pipeline langsung mati (404) tanpanya.
        // Penjagaan "board terakhir" di bawah tak menolong — ia cuma menghitung SEMUA
        // board, jadi sales tetap bisa dihapus selama masih ada board kanban lain.
        if ($board->type === 'pipeline') {
            return back()->with('status', 'Board sales tak bisa dihapus — Sales Pipeline hanya punya board ini.');
        }
        // cegah hapus bila masih ada entri, atau board terakhir
        if (Pipeline::where('category', $board->key)->exists()) {
            return back()->with('status', 'Board masih berisi task — pindahkan/hapus dulu.');
        }
        if (Category::count() <= 1) {
            return back()->with('status', 'Minimal harus ada satu board.');
        }

        $board->delete();

        return redirect()->route('pipelines.kanban')->with('status', 'Board dihapus.');
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'board';
        $key = $base;
        $i = 2;
        while (Category::where('key', $key)->exists()) {
            $key = $base.'_'.$i++;
        }

        return $key;
    }
}
