<?php

namespace Database\Seeders;

use App\Models\BoardColumn;
use App\Models\Category;
use App\Models\Output;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Database\Seeder;

class PipelineSeeder extends Seeder
{
    public function run(): void
    {
        $outputs = collect(['Reels', 'Story', 'Feed', 'TikTok', 'YouTube', 'Twitter/X'])
            ->map(fn ($n) => Output::firstOrCreate(['name' => $n]));

        $staff = User::orderBy('id')->pluck('id')->all();

        // endorse/agensi/coaching/speaker bukan board lagi — kini `jenis` pada kartu.
        $jenisList = array_keys(Pipeline::JENIS);

        // Satu board pipeline: `sales`. Halaman Sales Pipeline hanya menampilkan
        // kategori bertipe 'pipeline'; kolomnya = stage sales.
        $stages = [
            ['key' => 'lead', 'name' => 'Lead', 'color' => 'bg-slate-400'],
            ['key' => 'kontak', 'name' => 'Kontak', 'color' => 'bg-sky-500'],
            ['key' => 'nego', 'name' => 'Nego', 'color' => 'bg-amber-500'],
            ['key' => 'closing', 'name' => 'Closing', 'color' => 'bg-brand-600'],
            ['key' => 'deal', 'name' => 'Deal', 'color' => 'bg-emerald-500'],
        ];

        Category::updateOrCreate(['key' => 'sales'], ['name' => 'Sales', 'type' => 'pipeline']);

        // Kolom WAJIB ada: PipelineController@validated memvalidasi `progress`
        // terhadap board_columns board ini — tanpa kolom, tak ada nilai yg lolos.
        foreach ($stages as $i => $c) {
            BoardColumn::updateOrCreate(
                ['board_key' => 'sales', 'key' => $c['key']],
                ['name' => $c['name'], 'color' => $c['color'], 'position' => $i],
            );
        }

        // Buang kolom produksi sisa migrasi `board_columns` (yg menyemai SEMUA
        // kategori) — tanpa ini board sales punya 10 kolom, bukan 5.
        BoardColumn::where('board_key', 'sales')
            ->whereNotIn('key', array_column($stages, 'key'))->delete();

        $progress = array_column($stages, 'key');
        $payment = ['belum', 'dp', 'lunas'];
        $accounts = ['fk', 'ai_preneur'];
        $brands = [
            'Scarlett Whitening', 'MS Glow', 'Erigo', 'Somethinc', 'Wardah', 'Kahf',
            'Azarine', 'Avoskin', 'Rollover Reaction', 'Emina', 'Skintific', 'Barenbliss',
            'Tokopedia', 'Shopee Affiliate', 'Ruangguru', 'Niagahoster', 'Traveloka',
            'Kopi Kenangan', 'Grab Food', 'Bank Jago',
        ];

        foreach ($brands as $i => $brand) {
            $jenis = $jenisList[$i % count($jenisList)];
            $prog = $progress[$i % count($progress)];
            $pay = $payment[$i % count($payment)];
            $acc = $accounts[$i % count($accounts)];

            // nominal: mayoritas IDR, sebagian USD
            $isUsd = $i % 5 === 4;
            $postDate = \Carbon\Carbon::create(2026, 6, 1)->addDays($i * 3);

            $p = Pipeline::updateOrCreate(
                ['endorse' => "Endorse {$brand}"],
                [
                    'category'        => 'sales',
                    'jenis'           => $jenis,
                    'account'         => $acc,
                    'assigned_to'     => $staff ? $staff[$i % count($staff)] : null,
                    'progress'        => $prog,
                    'payment_status'  => $pay,
                    'tanggal_posting' => $postDate->toDateString(),
                    'tanggal_payment' => $pay === 'lunas' ? $postDate->copy()->addDays(7)->toDateString() : null,
                    'amount_idr'      => $isUsd ? null : (1_500_000 + ($i * 350_000)),
                    'amount_usd'      => $isUsd ? (150 + $i * 20) : null,
                    'link'            => $prog !== 'lead' ? "https://youtu.be/demo{$i}" : null,
                    'ke_gilang'       => ['belum', 'sudah', 'done'][$i % 3],
                    'notes'           => "Kampanye {$brand} — batch " . (intdiv($i, 5) + 1),
                ]
            );

            // attach 1-2 output deterministik
            $p->outputs()->sync([
                $outputs[$i % $outputs->count()]->id,
                $outputs[($i + 2) % $outputs->count()]->id,
            ]);
        }
    }
}
