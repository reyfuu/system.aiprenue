<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Support\AiOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

// CRUD transaksi pembukuan (pemasukan/pengeluaran) + upload bukti gambar.
// Akses = super_admin/it (via EnsureMenuAccess).
class TransactionController extends Controller
{
    /** Aturan validasi bersama create & update. */
    private function rules(): array
    {
        return [
            'type'        => ['required', Rule::in(array_keys(Transaction::TYPES))],
            'category'    => ['required', 'string', 'max:100'],
            'description' => 'nullable|string|max:255',
            'amount_idr'  => 'required|numeric|min:0',
            'date'        => 'required|date',
            'bukti'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data = $this->unggahBukti($request, $data);

        Transaction::create($data);

        return back()->with('status', 'Transaksi ditambahkan.');
    }

    /**
     * OCR struk: baca gambar via AI (9router vision), balikan JSON untuk prefill form.
     * Tidak menyimpan apa pun — user meninjau lalu submit lewat store() seperti biasa.
     * Endpoint JSON (bukan Inertia) karena dipanggil via fetch dari modal transaksi.
     */
    public function ocr(Request $request, AiOrchestrator $ai)
    {
        $request->validate([
            'bukti' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $file = $request->file('bukti');
        $base64 = base64_encode(file_get_contents($file->getRealPath()));

        return response()->json(
            $ai->bacaStruk($base64, $file->getMimeType(), Transaction::CATEGORIES)
        );
    }

    public function update(Request $request, Transaction $transaction)
    {
        $data = $request->validate($this->rules());
        $data = $this->unggahBukti($request, $data, $transaction);

        $transaction->update($data);

        return back()->with('status', 'Transaksi diperbarui.');
    }

    public function destroy(Transaction $transaction)
    {
        if ($transaction->bukti_path) {
            Storage::disk('public')->delete($transaction->bukti_path);
        }
        $transaction->delete();

        return back()->with('status', 'Transaksi dihapus.');
    }

    /** Upload file bukti ke disk public. Kalau edit & file baru → hapus yang lama. */
    private function unggahBukti(Request $request, array $data, ?Transaction $existing = null): array
    {
        if (! $request->hasFile('bukti')) {
            unset($data['bukti']);

            return $data;
        }

        if ($existing?->bukti_path) {
            Storage::disk('public')->delete($existing->bukti_path);
        }

        $data['bukti_path'] = $request->file('bukti')->store('bukti-transaksi', 'public');

        unset($data['bukti']);

        return $data;
    }
}
