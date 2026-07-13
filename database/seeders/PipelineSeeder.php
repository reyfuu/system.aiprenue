<?php

namespace Database\Seeders;

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

        $categories = ['endorse', 'agensi', 'coaching', 'speaker'];
        $progress = ['script', 'editing', 'progress', 'pending', 'done'];
        $payment = ['belum', 'dp', 'lunas'];
        $accounts = ['fk', 'ai_preneur'];
        $brands = [
            'Scarlett Whitening', 'MS Glow', 'Erigo', 'Somethinc', 'Wardah', 'Kahf',
            'Azarine', 'Avoskin', 'Rollover Reaction', 'Emina', 'Skintific', 'Barenbliss',
            'Tokopedia', 'Shopee Affiliate', 'Ruangguru', 'Niagahoster', 'Traveloka',
            'Kopi Kenangan', 'Grab Food', 'Bank Jago',
        ];

        foreach ($brands as $i => $brand) {
            $cat = $categories[$i % count($categories)];
            $prog = $progress[$i % count($progress)];
            $pay = $payment[$i % count($payment)];
            $acc = $accounts[$i % count($accounts)];

            // nominal: mayoritas IDR, sebagian USD
            $isUsd = $i % 5 === 4;
            $postDate = \Carbon\Carbon::create(2026, 6, 1)->addDays($i * 3);

            $p = Pipeline::updateOrCreate(
                ['endorse' => "Endorse {$brand}"],
                [
                    'category'        => $cat,
                    'account'         => $acc,
                    'assigned_to'     => $staff ? $staff[$i % count($staff)] : null,
                    'progress'        => $prog,
                    'payment_status'  => $pay,
                    'tanggal_posting' => $postDate->toDateString(),
                    'tanggal_payment' => $pay === 'lunas' ? $postDate->copy()->addDays(7)->toDateString() : null,
                    'amount_idr'      => $isUsd ? null : (1_500_000 + ($i * 350_000)),
                    'amount_usd'      => $isUsd ? (150 + $i * 20) : null,
                    'link'            => $prog !== 'script' ? "https://youtu.be/demo{$i}" : null,
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
