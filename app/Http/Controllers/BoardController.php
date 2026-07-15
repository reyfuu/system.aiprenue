<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Pipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BoardController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'section' => 'nullable|string|max:100',
            'type'    => 'nullable|in:kanban,pipeline',
        ]);
        $type = $data['type'] ?? 'kanban';

        $key = $this->uniqueKey($data['name']);
        Category::create([
            'key'     => $key,
            'name'    => trim($data['name']),
            'type'    => $type,
            'section' => filled($data['section'] ?? null) ? trim($data['section']) : null,
        ]);

        // Seed kolom default agar board baru langsung bisa dipakai (ada tombol +task)
        $defaults = [
            ['key' => 'script', 'name' => 'Script', 'color' => 'bg-purple-500'],
            ['key' => 'editing', 'name' => 'Editing', 'color' => 'bg-sky-500'],
            ['key' => 'progress', 'name' => 'Progress', 'color' => 'bg-brand-600'],
            ['key' => 'pending', 'name' => 'Pending', 'color' => 'bg-amber-500'],
            ['key' => 'done', 'name' => 'Done', 'color' => 'bg-emerald-500'],
        ];
        foreach ($defaults as $i => $col) {
            \App\Models\BoardColumn::create([
                'board_key' => $key,
                'key' => $col['key'],
                'name' => $col['name'],
                'color' => $col['color'],
                'position' => $i,
            ]);
        }

        // Board pipeline → ke tabel Pipeline; board kanban → ke papan kanban
        $route = $type === 'pipeline' ? 'pipelines.index' : 'pipelines.kanban';

        return redirect()->route($route, ['category' => $key])
            ->with('status', 'Board ditambahkan.');
    }

    public function update(Request $request, Category $board)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'section' => 'nullable|string|max:100',
        ]);
        $board->update([
            'name'    => trim($data['name']),
            'section' => filled($data['section'] ?? null) ? trim($data['section']) : null,
        ]);

        return redirect()->route('pipelines.kanban', ['category' => $board->key])
            ->with('status', 'Board diperbarui.');
    }

    public function destroy(Category $board)
    {
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
