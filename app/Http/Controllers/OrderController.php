<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Output;
use App\Support\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

// CRUD order/pesanan. Akses menu + batasan mutasi diatur EnsureMenuAccess.
class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('outputs');

        // Filter opsional (dikirim dari bar filter halaman)
        if ($request->filled('tipe_order')) {
            $query->where('tipe_order', $request->tipe_order);
        }
        if ($request->filled('account')) {
            $query->where('account', $request->account);
        }
        if ($request->filled('tipe_pembayaran')) {
            $query->where('tipe_pembayaran', $request->tipe_pembayaran);
        }
        // Filter output lewat pivot. whereHas, BUKAN join: join baru aman selama
        // filternya satu output (tiap order paling banyak cocok satu baris pivot).
        // Begitu ini menerima banyak output — spt chip jenis di Sales — join langsung
        // menduplikat ordernya. whereHas kebal dari awal.
        if ($request->filled('output')) {
            $query->whereHas('outputs', fn ($q) => $q->where('outputs.id', $request->output));
        }
        // Rentang tanggal deadline. Batas bawah/atas berdiri sendiri:
        // isi salah satu saja tetap jalan (mis. "sampai 31 Agu" tanpa batas awal).
        if ($request->filled('date_from')) {
            $query->whereDate('tanggal_deadline', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('tanggal_deadline', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('nama_customer', 'like', "%$s%")
                ->orWhere('telepon', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%")
                ->orWhere('kota', 'like', "%$s%"));
        }

        // 10 baris/halaman; withQueryString() agar filter ikut terbawa saat pindah halaman
        $orders = $query->latest('id')->paginate(10)->withQueryString();

        // Omzet: IDR & USD dipisah (angka asli), lalu gabungan dlm IDR pakai kurs.
        $rate = ExchangeRate::usdToIdr();
        $totalIdr = (float) Order::sum('total_idr');
        $totalUsd = (float) Order::sum('total_usd');

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['tipe_order', 'account', 'tipe_pembayaran', 'output', 'date_from', 'date_to', 'search']),
            'summary' => [
                'total' => Order::count(),
                'totalIdr' => $totalIdr,
                'totalUsd' => $totalUsd,
                'grandIdr' => $totalIdr + $totalUsd * $rate,   // dipajang sbg "Total Pembayaran"
                'dp' => Order::where('tipe_pembayaran', 'dp')->count(),
            ],
            'rate' => $rate,   // dipakai menghitung total per baris di tabel
            // Referensi dropdown (form + filter)
            'tipeOrder' => Order::TIPE_ORDER,
            'accounts' => Order::ACCOUNTS,
            'tipePembayaran' => Order::TIPE_PEMBAYARAN,
            'kotaList' => Order::kotaList(),
            'outputList' => Output::orderBy('name')->get(['id', 'name']),   // checkbox modal
        ]);
    }

    /** Aturan validasi bersama create & update. Hanya identitas inti order +
     *  tipe pembayaran yang wajib; detail operasional lain boleh dilengkapi
     *  belakangan.
     *
     *  Tipe pembayaran wajib meski kolomnya di DB punya default 'full'. Dua
     *  lapis itu beda tugas: `required` di sini memaksa MANUSIA memilih Full
     *  atau DP secara sadar (salah = pesan di form), sedangkan default di DB
     *  cuma jaring pengaman untuk penulis non-form (seeder, Order::create())
     *  supaya tak ada jalur yang berakhir jadi NOT NULL violation alias 500. */
    private function rules(): array
    {
        return [
            'tipe_order' => ['required', Rule::in(array_keys(Order::TIPE_ORDER))],
            'account' => ['required', Rule::in(array_keys(Order::ACCOUNTS))],
            'tanggal_deadline' => 'nullable|date',
            'nama_customer' => 'required|string|max:150',
            'telepon' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            // Kota bebas diketik (dataset wilayah cuma jadi saran di datalist) —
            // kota luar dataset & penulisan lokal tetap harus bisa masuk.
            'kota' => 'required|string|max:100',
            'alamat' => 'nullable|string|max:500',
            // `required`, bukan `nullable`: opsi "Belum ditentukan" mengirim string
            // kosong, ConvertEmptyStringsToNull mengubahnya jadi null, `nullable`
            // meloloskannya, dan null itu mendarat di kolom NOT NULL → 500.
            // Ditutup di sini supaya jadi pesan form, bukan SQL error.
            'tipe_pembayaran' => ['required', Rule::in(array_keys(Order::TIPE_PEMBAYARAN))],
            'tanggal_bayar' => 'nullable|date',
            // Nominal boleh belum diketahui saat order pertama kali dicatat.
            'total_idr' => ['nullable', 'numeric', 'min:0'],
            'total_usd' => ['nullable', 'numeric', 'min:0'],
            'outputs' => 'array',
            'outputs.*' => 'exists:outputs,id',
            'bukti_bayar' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',  // bukti transfer customer
            'invoice' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',  // invoice perusahaan, maks 5MB
        ];
    }

    /** Field upload → folder di disk 'public' (butuh `php artisan storage:link`). */
    private const FILES = ['bukti_bayar' => 'bukti-bayar', 'invoice' => 'invoice'];

    public function store(Request $request)
    {
        $data = $this->prepare($request);

        foreach (self::FILES as $field => $dir) {
            if ($request->hasFile($field)) {
                $data[$field] = $request->file($field)->store($dir, 'public');
            } else {
                unset($data[$field]);
            }
        }

        $order = Order::create($data);
        $order->outputs()->sync($request->input('outputs', []));

        return back()->with('status', 'Order ditambahkan.');
    }

    public function update(Request $request, Order $order)
    {
        $data = $this->prepare($request);

        foreach (self::FILES as $field => $dir) {
            if ($request->hasFile($field)) {
                // ganti file: buang yang lama agar tak jadi sampah di storage
                if ($order->$field) {
                    Storage::disk('public')->delete($order->$field);
                }
                $data[$field] = $request->file($field)->store($dir, 'public');
            } else {
                // tak ada file baru → pertahankan yang lama (jangan ditimpa null)
                unset($data[$field]);
            }
        }

        $order->update($data);
        $order->outputs()->sync($request->input('outputs', []));

        return back()->with('status', 'Order diperbarui.');
    }

    public function destroy(Order $order)
    {
        foreach (array_keys(self::FILES) as $field) {
            if ($order->$field) {
                Storage::disk('public')->delete($order->$field);
            }
        }

        $order->delete();

        return back()->with('status', 'Order dihapus.');
    }

    /** Validasi + default nominal. Kolom nominal NOT NULL → jangan kirim null. */
    private function prepare(Request $request): array
    {
        $data = $request->validate($this->rules());
        $data['total_idr'] = $data['total_idr'] ?? 0;
        $data['total_usd'] = $data['total_usd'] ?? 0;
        // `outputs` bukan kolom di tabel orders — masuk lewat pivot (sync di store/update).
        // Kalau ikut terbawa ke Order::create(), Eloquent melempar (kolom tak ada).
        unset($data['outputs']);

        return $data;
    }
}
