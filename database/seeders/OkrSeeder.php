<?php

namespace Database\Seeders;

use App\Models\OkrObjective;
use Illuminate\Database\Seeder;

/** Data dummy OKR untuk dev: variasi periode, tingkat penyelesaian, dan
 *  deadline (ada yang lewat) supaya "helicopter view" di halaman terlihat hidup. */
class OkrSeeder extends Seeder
{
    public function run(): void
    {
        // Tanggal relatif hari ini supaya status "lewat deadline" tetap masuk akal
        // kapan pun seeder dijalankan.
        $lewat  = now()->subWeeks(2)->toDateString();   // deadline sudah lewat
        $dekat  = now()->addWeeks(3)->toDateString();    // mendekat
        $jauh   = now()->addMonths(2)->toDateString();   // masih lama

        $data = [
            [
                'title' => 'Perkuat brand awareness Raveloux',
                'period' => 'Q3 2026', 'owner' => 'Tim Marketing', 'deadline' => $jauh,
                'description' => 'Bangun kesadaran merek accessible-luxury di kalangan wanita 22–40.',
                'kr' => [
                    ['Capai 50rb follower Instagram', true],
                    ['Kolaborasi dengan 5 micro-influencer', true],
                    ['Tayangkan 3 seri konten "penjahit pribadi"', false],
                ],
            ],
            [
                'title' => 'Naikkan konversi penjualan custom dress',
                'period' => 'Q3 2026', 'owner' => 'Tim Sales', 'deadline' => $dekat,
                'description' => 'Ubah minat jadi order tanpa hard-sell.',
                'kr' => [
                    ['Rasio konsultasi → order 30%', false],
                    ['Rata-rata respons chat < 1 jam', true],
                    ['Kumpulkan 20 testimoni pelanggan', false],
                ],
            ],
            [
                'title' => 'Tingkatkan retensi pelanggan',
                'period' => 'Q2 2026', 'owner' => 'Customer Success', 'deadline' => $lewat,
                'description' => 'Dorong pelanggan lama pesan ulang.',
                'kr' => [
                    ['30% pelanggan repeat order', false],
                    ['Program loyalitas berjalan', true],
                ],
            ],
            [
                'title' => 'Konten pipeline konsisten',
                'period' => 'Q3 2026', 'owner' => 'Tim Content', 'deadline' => $jauh,
                'description' => 'Produksi konten stabil tiap minggu.',
                'kr' => [
                    ['Jadwal 12 konten/bulan tersusun', true],
                    ['Stok 1 bulan konten siap tayang', true],
                ],
            ],
            [
                'title' => 'Efisiensi operasional produksi',
                'period' => 'Q3 2026', 'owner' => 'Operasional', 'deadline' => null,
                'description' => 'Pangkas waktu produksi tanpa turunkan kualitas.',
                'kr' => [
                    ['Lead time jahit turun jadi 10 hari', false],
                    ['SOP quality check terdokumentasi', false],
                    ['Angka revisi < 1 per order', false],
                ],
            ],
        ];

        foreach ($data as $row) {
            $obj = OkrObjective::create([
                'title' => $row['title'],
                'period' => $row['period'],
                'owner' => $row['owner'],
                'deadline' => $row['deadline'],
                'description' => $row['description'],
            ]);
            $obj->keyResults()->createMany(array_map(
                fn ($kr) => ['title' => $kr[0], 'completed' => $kr[1]],
                $row['kr'],
            ));
            $obj->syncKanbanBoard(); // demo: tiap objective langsung punya board Kanban + kartu
        }
    }
}
