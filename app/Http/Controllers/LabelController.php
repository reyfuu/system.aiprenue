<?php

namespace App\Http\Controllers;

use App\Models\Label;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kelola definisi label kartu — HANYA OWNER.
 *
 * Warna dikunci ke palet safelist (Label::COLORS); warna lain tak ter-render
 * Tailwind di produksi. Kartu menyimpan snapshot {name,color}-nya sendiri, jadi
 * mengubah/menghapus label di sini tak menyentuh kartu yang sudah ada.
 */
class LabelController extends Controller
{
    public function store(Request $request)
    {
        $this->pastikanOwner($request);
        Label::create($this->validated($request));

        return back()->with('status', 'Label ditambahkan.');
    }

    public function update(Request $request, Label $label)
    {
        $this->pastikanOwner($request);
        $label->update($this->validated($request));

        return back()->with('status', 'Label diperbarui.');
    }

    public function destroy(Request $request, Label $label)
    {
        $this->pastikanOwner($request);
        $label->delete();

        return back()->with('status', 'Label dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'  => 'required|string|max:50',
            'color' => ['required', Rule::in(Label::COLORS)],
        ]);
    }

    /** Gate owner-only. Bukan canManage: manager/admin/it pun tak boleh. */
    private function pastikanOwner(Request $request): void
    {
        abort_unless($request->user()?->role === 'owner', 403, 'Hanya owner yang boleh mengelola label.');
    }
}
