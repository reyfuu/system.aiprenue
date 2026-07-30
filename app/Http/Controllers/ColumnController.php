<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Pipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /** Urutan kolom sesudah drag. Yang dikirim seluruh kolom board, bukan
     *  "kolom X ke posisi 3" — bentuk itu tak bisa setengah jadi.
     *
     *  Beda sengaja dari PipelineController@reorder yang membolehkan kiriman
     *  sebagian (kartu: cuma isi kolom tujuan, posisi kolom asal boleh berlubang):
     *  kolom cuma punya SATU daftar per board, jadi kiriman sebagian tak pernah sah
     *  dan malah bikin position kembar — dan position kembar = urutan kolom acak
     *  di orderBy('position'), persis keluhan yang mau ditutup fitur ini.
     */
    public function reorder(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $columns = BoardColumn::whereIn('id', $data['ids'])->get();

        abort_if($columns->count() !== count($data['ids']), 404, 'Ada kolom yang tak ditemukan.');

        // Board diambil DARI KOLOMNYA, bukan dari request: id kolom unik global,
        // jadi tanpa pagar ini satu kiriman bisa menata ulang kolom board lain.
        $board = $columns->pluck('board_key')->unique();
        abort_if($board->count() > 1, 422, 'Kolom berasal dari board berbeda.');

        // Wajib memuat SEMUA kolom board — lihat alasan position kembar di atas.
        abort_if(
            BoardColumn::where('board_key', $board->first())->count() !== count($data['ids']),
            422,
            'Urutan wajib memuat semua kolom board.'
        );

        // Transaksi: separuh tersimpan = urutan kolom kacau di layar semua orang.
        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $i => $id) {
                BoardColumn::where('id', $id)->update(['position' => $i]);
            }
        });

        return response()->json(['ok' => true]);
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
