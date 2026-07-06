<?php

namespace Database\Seeders;

use App\Models\Output;
use App\Models\Pipeline;
use Illuminate\Database\Seeder;

class PipelineSeeder extends Seeder
{
    public function run(): void
    {
        // Master OUTPUT tags (kolom E)
        $outputs = [
            'Youtube'      => 'red',
            'Reels/TikTok' => 'blue',
            'Agency'       => 'slate',
            'Foto'         => 'green',
            'Video'        => 'indigo',
        ];
        foreach ($outputs as $name => $color) {
            Output::firstOrCreate(['name' => $name], ['color' => $color]);
        }
        $o = Output::pluck('id', 'name');

        // Data dari sheet PIPELINE FK-AI PRENEUR (kolom A–L)
        // [account, endorse, [outputs], progress, tgl_posting, tgl_payment, payment_status, idr, usd, notes]
        $rows = [
            ['fk', 'Halo AI', ['Youtube'], 'done', null, null, 'lunas', 6_000_000, null, null],
            ['fk', 'Halo AI', ['Reels/TikTok'], 'done', null, null, 'lunas', 4_890_000, null, null],
            ['fk', 'World ID', ['Reels/TikTok'], 'done', null, null, 'lunas', 4_500_000, null, null],
            ['fk', 'CapCut (Zeelot)', ['Reels/TikTok'], 'done', null, null, 'lunas', null, 249, null],
            ['fk', 'Seedance (Katlas)', ['Youtube'], 'done', '2026-04-03', null, 'lunas', null, 300, null],
            ['fk', 'Seedance JIVE', ['Youtube'], 'done', '2026-02-13', null, 'lunas', null, 500, null],
            ['fk', 'BytePlus', ['Reels/TikTok'], 'done', null, null, 'lunas', 2_750_000, null, null],
            ['fk', 'Pippit AI', ['Reels/TikTok'], 'done', '2026-04-06', null, 'lunas', 2_500_000, null, null],
            ['fk', 'Dreamina', ['Reels/TikTok'], 'progress', null, null, 'dp', null, 800, 'Masih kurang 3 konten (1 udah selesai)'],
            ['ai_preneur', 'Kling Omni', ['Reels/TikTok'], 'done', null, null, 'lunas', null, 169, null],
            ['ai_preneur', 'Custom Lashes', ['Agency', 'Foto'], 'done', null, null, 'lunas', null, 49.82, '9 Foto Feed'],
            ['ai_preneur', 'Kling Omni 2', ['Reels/TikTok'], 'done', null, null, 'lunas', null, 169, null],
            ['ai_preneur', 'Etira', ['Agency', 'Video'], 'done', null, null, 'lunas', 2_000_000, null, null],
            ['ai_preneur', 'Etira 2', ['Agency', 'Video'], 'done', '2026-03-11', '2026-04-29', 'lunas', 2_000_000, null, null],
            ['fk', 'Trae Solo', ['Youtube'], 'done', null, null, 'lunas', 8_000_000, null, null],
            ['fk', 'Seedance JIVE Sammie', ['Youtube'], 'done', '2026-04-21', null, 'lunas', null, 500, 'Handle Uli'],
            ['ai_preneur', 'TapNow', ['Reels/TikTok'], 'done', null, '2026-03-31', 'lunas', null, 299, null],
            ['ai_preneur', 'Dreamina CapCut', ['Reels/TikTok'], 'done', null, null, 'lunas', null, 180, null],
            ['fk', 'Pippit AI', ['Reels/TikTok'], 'done', '2026-06-19', '2026-06-19', 'lunas', 8_000_000, null, 'Handle Uli'],
            ['ai_preneur', 'Terabox Luar', ['Reels/TikTok'], 'done', null, '2026-04-23', 'lunas', null, 200, 'Handle Uli'],
            ['ai_preneur', 'SESE Video Ads', ['Agency', 'Video'], 'done', null, '2026-04-02', 'lunas', null, 99, 'Handle Uli'],
            ['fk', 'Terabox Indo', ['Reels/TikTok'], 'done', '2026-04-20', '2026-04-23', 'lunas', 3_000_000, null, null],
            ['fk', 'Zenva AI', ['Reels/TikTok'], 'done', '2026-04-21', '2026-04-29', 'lunas', 2_200_000, null, null],
            ['fk', 'Qwen AI', ['Reels/TikTok'], 'done', '2026-05-25', '2026-05-27', 'lunas', null, 318, 'Handle Uli'],
            ['fk', 'Everpro', ['Reels/TikTok'], 'done', '2026-05-07', '2026-05-08', 'lunas', 2_750_000, null, null],
            ['ai_preneur', 'Etira TVC', ['Video'], 'done', null, null, 'lunas', 1_300_000, null, null],
            ['ai_preneur', 'Creatify', ['Reels/TikTok'], 'done', '2026-05-07', '2026-05-12', 'lunas', null, 150, null],
            ['ai_preneur', 'Prism AI', ['Reels/TikTok'], 'done', '2026-05-15', '2026-05-25', 'lunas', null, 200, null],
            ['fk', 'CBI visit jakarta', ['Reels/TikTok'], 'done', '2026-05-08', '2026-05-08', 'lunas', 4_900_000, null, 'Handle Uli'],
            ['fk', 'Everpro 2', ['Reels/TikTok'], 'done', '2026-05-28', '2026-05-28', 'lunas', 2_750_000, null, null],
            ['fk', 'Halo AI', ['Youtube'], 'done', '2026-05-20', '2026-05-20', 'lunas', 7_500_000, null, null],
            ['fk', 'Lumina', ['Reels/TikTok'], 'editing', null, '2026-06-19', 'lunas', null, 250, null],
            ['fk', 'Halo AI Linkedin', ['Reels/TikTok'], 'done', '2026-05-22', '2026-05-25', 'lunas', 1_750_000, null, null],
            ['fk', 'Emergent AI', ['Reels/TikTok'], 'done', '2026-05-29', '2026-06-06', 'belum', null, 233, null],
            ['fk', 'Capcut (Pippit)', ['Reels/TikTok'], 'done', '2026-05-26', null, 'lunas', 2_900_000, null, null],
            ['fk', 'GAMSGO', ['Youtube'], 'done', null, '2026-06-19', 'lunas', null, 300, 'Handle uli'],
        ];

        foreach ($rows as $r) {
            $pipeline = Pipeline::create([
                'account' => $r[0],
                'endorse' => $r[1],
                'progress' => $r[3],
                'tanggal_posting' => $r[4],
                'tanggal_payment' => $r[5],
                'payment_status' => $r[6],
                'amount_idr' => $r[7],
                'amount_usd' => $r[8],
                'notes' => $r[9],
            ]);
            $ids = array_map(fn ($name) => $o[$name], $r[2]);
            $pipeline->outputs()->sync($ids);
        }
    }
}
