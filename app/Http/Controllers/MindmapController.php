<?php

namespace App\Http\Controllers;

use App\Models\Mindmap;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MindmapController extends Controller
{
    /** Daftar mindmap (galeri). */
    public function index()
    {
        return Inertia::render('Mindmap/Index', [
            'mindmaps' => Mindmap::with('user:id,name')->latest('updated_at')->get()
                ->map(fn ($m) => [
                    'id'      => $m->id,
                    'title'   => $m->title,
                    'owner'   => $m->user?->name,
                    'updated' => $m->updated_at?->diffForHumans(),
                ]),
            'canManage' => auth()->user()->canManage(),
        ]);
    }

    /** Buat mindmap baru → langsung buka editornya. */
    public function store(Request $request)
    {
        $data = $request->validate(['title' => 'nullable|string|max:120']);

        $mindmap = Mindmap::create([
            'user_id' => auth()->id(),
            'title'   => trim($data['title'] ?? '') ?: 'Mindmap Baru',
            'data'    => null, // diisi struktur default di frontend saat pertama dibuka
        ]);

        return redirect()->route('mindmaps.show', $mindmap);
    }

    /** Editor mindmap. */
    public function show(Mindmap $mindmap)
    {
        return Inertia::render('Mindmap/Edit', [
            'mindmap' => [
                'id'    => $mindmap->id,
                'title' => $mindmap->title,
                'data'  => $mindmap->data, // null bila baru → frontend pakai struktur default
            ],
            'canManage' => auth()->user()->canManage(),
        ]);
    }

    /** Simpan perubahan (judul + struktur node). */
    public function update(Request $request, Mindmap $mindmap)
    {
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'data'  => 'required|array',
        ]);
        $mindmap->update($data);

        return response()->json(['ok' => true]); // disimpan via fetch dari editor (bukan Inertia)
    }

    public function destroy(Mindmap $mindmap)
    {
        $mindmap->delete();

        return redirect()->route('mindmaps.index')->with('status', 'Mindmap dihapus.');
    }
}
