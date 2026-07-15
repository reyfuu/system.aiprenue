<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

// CRUD order/pesanan. Akses menu + batasan mutasi diatur EnsureMenuAccess.
class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::query();

        // Filter opsional (dikirim dari bar filter halaman)
        if ($request->filled('tipe_order')) {
            $query->where('tipe_order', $request->tipe_order);
        }
        if ($request->filled('prioritas')) {
            $query->where('prioritas', $request->prioritas);
        }
        if ($request->filled('tipe_pembayaran')) {
            $query->where('tipe_pembayaran', $request->tipe_pembayaran);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('nama_customer', 'like', "%$s%")
                ->orWhere('telepon', 'like', "%$s%")
                ->orWhere('kota', 'like', "%$s%"));
        }

        // 10 baris/halaman; withQueryString() agar filter ikut terbawa saat pindah halaman
        $orders = $query->latest('id')->paginate(10)->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders'  => $orders,
            'filters' => $request->only(['tipe_order', 'prioritas', 'tipe_pembayaran', 'search']),
            'summary' => [
                'total'      => Order::count(),
                'urgent'     => Order::whereIn('prioritas', ['urgent', 'super_urgent'])->count(),
                'nilai'      => (float) Order::sum('total_pembayaran'),
                'dp'         => Order::where('tipe_pembayaran', 'dp')->count(),
            ],
            // Referensi dropdown (form + filter)
            'tipeOrder'      => Order::TIPE_ORDER,
            'prioritas'      => Order::PRIORITAS,
            'tipePembayaran' => Order::TIPE_PEMBAYARAN,
            'kotaList'       => Order::kotaList(),
        ]);
    }

    /** Aturan validasi bersama create & update. */
    private function rules(): array
    {
        return [
            'tipe_order'       => ['required', Rule::in(array_keys(Order::TIPE_ORDER))],
            'prioritas'        => ['nullable', Rule::in(array_keys(Order::PRIORITAS))],
            'tanggal_deadline' => 'nullable|date',
            'nama_customer'    => 'required|string|max:150',
            'telepon'          => 'nullable|string|max:30',
            // kota wajib salah satu dari dataset wilayah — cegah typo/isian ngawur
            'kota'             => ['nullable', Rule::in(Order::kotaList())],
            'alamat'           => 'nullable|string|max:500',
            'tipe_pembayaran'  => ['required', Rule::in(array_keys(Order::TIPE_PEMBAYARAN))],
            'tanggal_bayar'    => 'nullable|date',
            'total_pembayaran' => 'nullable|numeric|min:0',
            'bukti_bayar'      => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048', // maks 2MB
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['total_pembayaran'] = $data['total_pembayaran'] ?? 0;

        // simpan file ke disk 'public' (butuh `php artisan storage:link`)
        if ($request->hasFile('bukti_bayar')) {
            $data['bukti_bayar'] = $request->file('bukti_bayar')->store('bukti-bayar', 'public');
        }

        Order::create($data);

        return back()->with('status', 'Order ditambahkan.');
    }

    public function update(Request $request, Order $order)
    {
        $data = $request->validate($this->rules());
        $data['total_pembayaran'] = $data['total_pembayaran'] ?? 0;

        if ($request->hasFile('bukti_bayar')) {
            // ganti file: buang yang lama agar tak jadi sampah di storage
            if ($order->bukti_bayar) {
                Storage::disk('public')->delete($order->bukti_bayar);
            }
            $data['bukti_bayar'] = $request->file('bukti_bayar')->store('bukti-bayar', 'public');
        } else {
            // tak ada file baru → pertahankan yang lama (jangan ditimpa null)
            unset($data['bukti_bayar']);
        }

        $order->update($data);

        return back()->with('status', 'Order diperbarui.');
    }

    public function destroy(Order $order)
    {
        if ($order->bukti_bayar) {
            Storage::disk('public')->delete($order->bukti_bayar);
        }

        $order->delete();

        return back()->with('status', 'Order dihapus.');
    }
}
