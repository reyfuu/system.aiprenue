<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $tipe = ['coaching', 'endorse', 'speaker', 'agency'];
        $prioritas = ['normal', 'urgent', 'super_urgent', null];
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
            Order::updateOrCreate(
                ['nama_customer' => $name],
                [
                    'tipe_order'       => $tipe[$i % count($tipe)],
                    'prioritas'        => $prioritas[$i % count($prioritas)],
                    'tanggal_deadline' => $deadline->toDateString(),
                    'telepon'          => '08' . str_pad((string) (1234567890 + $i), 10, '0', STR_PAD_LEFT),
                    'kota'             => $kota[$i % count($kota)],
                    'alamat'           => 'Jl. Contoh No. ' . ($i + 1),
                    'tipe_pembayaran'  => $tipeBayar,
                    // DP dibayar di muka; full dibayar saat deadline
                    'tanggal_bayar'    => $tipeBayar === 'dp' ? $deadline->copy()->subDays(14)->toDateString() : $deadline->toDateString(),
                    'bukti_bayar'      => null,   // file contoh tak diseed — diunggah lewat form
                    'total_pembayaran' => 2_500_000 + ($i * 750_000),
                ]
            );
        }
    }
}
