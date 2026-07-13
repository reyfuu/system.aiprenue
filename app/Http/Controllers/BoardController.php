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
        $request->validate(['name' => 'required|string|max:100']);

        $key = $this->uniqueKey($request->name);
        Category::create(['key' => $key, 'name' => trim($request->name)]);

        return redirect()->route('pipelines.kanban', ['category' => $key])
            ->with('status', 'Board ditambahkan.');
    }

    public function update(Request $request, Category $board)
    {
        $request->validate(['name' => 'required|string|max:100']);
        $board->update(['name' => trim($request->name)]);

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
