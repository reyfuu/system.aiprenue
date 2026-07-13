<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class PembukuanSeeder extends Seeder
{
    public function run(): void
    {
        // Bulan Juni, Juli, Agustus 2026
        $months = [6, 7, 8];

        // pemasukan & pengeluaran per bulan (dummy realistis)
        $income = [
            ['category' => 'Endorse',  'base' => 12_000_000],
            ['category' => 'Agensi',   'base' => 8_500_000],
            ['category' => 'Coaching', 'base' => 5_000_000],
        ];
        $expense = [
            ['category' => 'Operasional',  'base' => 3_200_000],
            ['category' => 'Gaji Tim',     'base' => 6_000_000],
            ['category' => 'Iklan/Ads',    'base' => 2_500_000],
            ['category' => 'Alat/Software', 'base' => 1_200_000],
        ];

        foreach ($months as $mi => $month) {
            $growth = 1 + $mi * 0.12; // naik tiap bulan

            foreach ($income as $r) {
                Transaction::updateOrCreate(
                    ['type' => 'pemasukan', 'category' => $r['category'], 'date' => "2026-{$month}-05"],
                    ['description' => "Pemasukan {$r['category']}", 'amount_idr' => round($r['base'] * $growth)]
                );
            }
            foreach ($expense as $r) {
                Transaction::updateOrCreate(
                    ['type' => 'pengeluaran', 'category' => $r['category'], 'date' => "2026-{$month}-10"],
                    ['description' => "Pengeluaran {$r['category']}", 'amount_idr' => round($r['base'] * $growth)]
                );
            }
        }

        // inventaris barang per bulan
        $items = [
            ['name' => 'Kamera Sony ZV-E10',   'qty' => 2, 'unit' => 9_500_000],
            ['name' => 'Lighting Softbox',      'qty' => 4, 'unit' => 850_000],
            ['name' => 'Microphone Rode',       'qty' => 3, 'unit' => 2_200_000],
            ['name' => 'Tripod & Gimbal',       'qty' => 5, 'unit' => 1_100_000],
            ['name' => 'Stok Produk Endorse',   'qty' => 40, 'unit' => 150_000],
        ];

        foreach ($months as $mi => $month) {
            foreach ($items as $it) {
                Inventory::updateOrCreate(
                    ['name' => $it['name'], 'month' => "2026-{$month}-01"],
                    ['qty' => $it['qty'] + $mi, 'unit_value_idr' => $it['unit']]
                );
            }
        }
    }
}
