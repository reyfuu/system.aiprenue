<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// CRUD transaksi pembukuan (pemasukan/pengeluaran). Akses = super_admin/it (via EnsureMenuAccess).
class TransactionController extends Controller
{
    // Aturan validasi bersama create & update
    private function rules(): array
    {
        return [
            'type'        => ['required', Rule::in(array_keys(Transaction::TYPES))], // pemasukan/pengeluaran
            'category'    => 'required|string|max:100',   // kategori bebas
            'description' => 'nullable|string|max:255',    // keterangan opsional
            'amount_idr'  => 'required|numeric|min:0',     // nominal
            'date'        => 'required|date',              // tanggal transaksi
        ];
    }

    public function store(Request $request)
    {
        Transaction::create($request->validate($this->rules()));

        return back()->with('status', 'Transaksi ditambahkan.');
    }

    public function update(Request $request, Transaction $transaction)
    {
        $transaction->update($request->validate($this->rules()));

        return back()->with('status', 'Transaksi diperbarui.');
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();

        return back()->with('status', 'Transaksi dihapus.');
    }
}
