<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Pipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ColumnController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'board_key' => 'required|string',
            'name'      => 'required|string|max:60',
        ]);

        BoardColumn::create([
            'board_key' => $data['board_key'],
            'key'       => $this->uniqueKey($data['board_key'], $data['name']),
            'name'      => trim($data['name']),
            'color'     => 'bg-slate-400',
            'position'  => (int) BoardColumn::where('board_key', $data['board_key'])->max('position') + 1,
        ]);

        return redirect()->route('pipelines.kanban', ['category' => $data['board_key']])
            ->with('status', 'Kolom ditambahkan.');
    }

    public function update(Request $request, BoardColumn $column)
    {
        $request->validate(['name' => 'required|string|max:60']);
        $column->update(['name' => trim($request->name)]);

        return redirect()->route('pipelines.kanban', ['category' => $column->board_key])
            ->with('status', 'Kolom diperbarui.');
    }

    public function destroy(BoardColumn $column)
    {
        if (Pipeline::where('category', $column->board_key)->where('progress', $column->key)->exists()) {
            return back()->with('status', 'Kolom masih berisi kartu — pindahkan dulu.');
        }
        if (BoardColumn::where('board_key', $column->board_key)->count() <= 1) {
            return back()->with('status', 'Minimal harus ada satu kolom.');
        }

        $board = $column->board_key;
        $column->delete();

        return redirect()->route('pipelines.kanban', ['category' => $board])
            ->with('status', 'Kolom dihapus.');
    }

    private function uniqueKey(string $boardKey, string $name): string
    {
        $base = Str::slug($name, '_') ?: 'kolom';
        $key = $base;
        $i = 2;
        while (BoardColumn::where('board_key', $boardKey)->where('key', $key)->exists()) {
            $key = $base.'_'.$i++;
        }

        return $key;
    }
}
