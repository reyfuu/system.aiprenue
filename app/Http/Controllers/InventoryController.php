<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Support\AiOrchestrator;
use Illuminate\Http\Request;

// CRUD inventaris barang pembukuan. Akses = super_admin/it (via EnsureMenuAccess).
class InventoryController extends Controller
{
    // Aturan validasi bersama create & update
    private function rules(): array
    {
        return [
            'name'           => 'required|string|max:150',  // nama barang
            'qty'            => 'required|integer|min:0',    // jumlah
            'unit_value_idr' => 'required|numeric|min:0',    // nilai per unit
            'month'          => 'required|date',             // bulan snapshot (tgl 1)
        ];
    }

    /**
     * OCR inventaris: baca foto barang/nota via AI, balikan JSON untuk prefill form.
     * Tidak menyimpan apa pun — user meninjau lalu submit lewat store() seperti biasa.
     * Endpoint JSON (bukan Inertia) karena dipanggil via fetch dari modal inventaris.
     */
    public function ocr(Request $request, AiOrchestrator $ai)
    {
        $request->validate([
            'gambar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $file = $request->file('gambar');
        $base64 = base64_encode(file_get_contents($file->getRealPath()));

        return response()->json($ai->bacaInventaris($base64, $file->getMimeType()));
    }

    public function store(Request $request)
    {
        Inventory::create($request->validate($this->rules()));

        return back()->with('status', 'Inventaris ditambahkan.');
    }

    public function update(Request $request, Inventory $inventory)
    {
        $inventory->update($request->validate($this->rules()));

        return back()->with('status', 'Inventaris diperbarui.');
    }

    public function destroy(Inventory $inventory)
    {
        $inventory->delete();

        return back()->with('status', 'Inventaris dihapus.');
    }
}
