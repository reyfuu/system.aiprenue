<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Output;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        // Output diseed PipelineSeeder (jalan lebih dulu) & migrasi 100000. Diambil
        // urut `id` supaya pembagian di bawah deterministik — orderBy('name') bikin
        // urutannya berubah tiap kali ada output baru.
        $outputs = Output::orderBy('id')->get();

        $tipe = array_keys(Order::TIPE_ORDER);
        $akun = array_keys(Order::ACCOUNTS);
        $bayar = ['full', 'dp'];
        $kota = ['Kota Jakarta Selatan', 'Kota Bandung', 'Kota Surabaya', 'Kota Yogyakarta',
            'Kota Semarang', 'Kota Denpasar', 'Kota Medan', 'Singapore', 'Australia', 'Johor Bahru', 'Miri'];

        $names = [
            'Anisa Rahma', 'Bella Safira', 'Citra Dewi', 'Dinda Ayu', 'Erika Putri',
            'Fitri Handayani', 'Gita Lestari', 'Hana Permata', 'Indah Sari', 'Jasmine Aulia',
            'Kirana Wulan', 'Laras Ayu', 'Maya Anggraini', 'Nadia Salsabila', 'Olivia Tan',
            'Putri Maharani', 'Qonita Zahra', 'Rina Amelia', 'Sasha Kirana', 'Tiara Nabila',
        ];

        foreach ($names as $i => $name) {
            $deadline = \Carbon\Carbon::create(2026, 8, 1)->addDays($i * 2);
            $tipeBayar = $bayar[$i % 2];

            // updateOrCreate by nama → idempotent, aman dijalankan ulang
            $order = Order::updateOrCreate(
                ['nama_customer' => $name],
                [
                    'tipe_order'       => $tipe[$i % count($tipe)],
                    'account'          => $akun[$i % count($akun)],
                    'tanggal_deadline' => $deadline->toDateString(),
                    'telepon'          => '08' . str_pad((string) (1234567890 + $i), 10, '0', STR_PAD_LEFT),
                    'email'            => \Illuminate\Support\Str::slug($name, '.') . '@example.com',
                    'kota'             => $kota[$i % count($kota)],
                    'alamat'           => 'Jl. Contoh No. ' . ($i + 1),
                    'tipe_pembayaran'  => $tipeBayar,
                    // DP dibayar di muka; full dibayar saat deadline
                    'tanggal_bayar'    => $tipeBayar === 'dp' ? $deadline->copy()->subDays(14)->toDateString() : $deadline->toDateString(),
                    'bukti_bayar'      => null,   // file contoh tak diseed — diunggah lewat form
                    'invoice'          => null,
                    // sebagian order USD (tiap kelipatan 5) → uji total gabungan
                    'total_idr'        => $i % 5 === 4 ? 0 : 2_500_000 + ($i * 750_000),
                    'total_usd'        => $i % 5 === 4 ? 250 + ($i * 25) : 0,
                ]
            );

            // Output: sebaran sengaja dibikin timpang supaya filternya benar-benar
            // teruji — tiap ke-7 order TANPA output (menguji em-dash & "hilang saat
            // difilter"), sisanya 1–3 output. sync() = idempoten, aman dijalankan ulang.
            $order->outputs()->sync($this->outputsFor($i, $outputs));
        }
    }

    /** @return array<int,int> id output untuk order ke-$i (deterministik) */
    private function outputsFor(int $i, \Illuminate\Support\Collection $outputs): array
    {
        if ($outputs->isEmpty() || $i % 7 === 6) {
            return [];
        }

        $n = $outputs->count();
        // 1–3 output; offset ganjil (0,+3,+5) supaya kombinasinya tak selalu berdekatan
        $jumlah = ($i % 3) + 1;
        $ids = [];
        foreach ([0, 3, 5] as $k => $offset) {
            if ($k >= $jumlah) {
                break;
            }
            $ids[] = $outputs[($i + $offset) % $n]->id;
        }

        return array_values(array_unique($ids));
    }
}
