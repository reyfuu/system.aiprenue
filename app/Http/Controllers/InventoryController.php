<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
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
