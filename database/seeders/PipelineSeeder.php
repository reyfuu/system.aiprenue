<?php

namespace Database\Seeders;

use App\Models\Output;
use App\Models\Pipeline;
use Illuminate\Database\Seeder;

class PipelineSeeder extends Seeder
{
    public function run(): void
    {
        // Master OUTPUT tags (kolom E) — opsi baru
        $outputs = [
            'TikTok/Reels' => 'blue',
            'YouTube'      => 'red',
            'Feed'         => 'green',
            'Foto'         => 'amber',
            'Video'        => 'indigo',
            'Posting Only' => 'slate',
        ];
        foreach ($outputs as $name => $color) {
            Output::firstOrCreate(['name' => $name], ['color' => $color]);
        }
        $o = Output::pluck('id', 'name');

        // Data lengkap dari sheet PIPELINE FK-AI PRENEUR (kolom A–N)
        // [category, account, endorse, [outputs], progress, tgl_posting, tgl_payment, payment_status, idr, usd, notes, ke_gilang, catatan]
        $rows = [
            ['endorse', 'fk', 'Halo AI', ['YouTube'], 'done', null, null, 'lunas', 6_000_000, null, null, 'sudah', '325'],
            ['endorse', 'fk', 'Halo AI', ['TikTok/Reels'], 'done', null, null, 'lunas', 4_890_000, null, null, 'sudah', '200'],
            ['endorse', 'fk', 'World ID', ['TikTok/Reels'], 'done', null, null, 'lunas', 4_500_000, null, null, 'sudah', '200'],
            ['endorse', 'fk', 'CapCut (Zeelot)', ['TikTok/Reels'], 'done', null, null, 'lunas', null, 249, null, 'sudah', '200'],
            ['endorse', 'fk', 'Seedance (Katlas)', ['YouTube'], 'done', '2026-04-03', null, 'lunas', null, 300, null, 'sudah', '325'],
            ['endorse', 'fk', 'Seedance JIVE', ['YouTube'], 'done', '2026-02-13', null, 'lunas', null, 500, null, 'sudah', '325'],
            ['endorse', 'fk', 'BytePlus', ['TikTok/Reels'], 'done', null, null, 'lunas', 2_750_000, null, null, 'sudah', '150'],
            ['endorse', 'fk', 'Pippit AI', ['TikTok/Reels'], 'done', '2026-04-06', null, 'lunas', 2_500_000, null, null, 'sudah', '150'],
            ['endorse', 'fk', 'Dreamina', ['TikTok/Reels'], 'progress', null, null, 'dp', null, 800, null, 'belum', 'Masih kurang 3 konten (1 udah selesai)'],
            ['endorse', 'ai_preneur', 'Kling Omni', ['TikTok/Reels'], 'done', null, null, 'lunas', null, 169, null, 'sudah', '150'],
            ['agensi', 'ai_preneur', 'Custom Lashes', ['Foto', 'Feed'], 'done', null, null, 'lunas', null, 49.82, null, 'belum', '9 Foto Feed'],
            ['endorse', 'ai_preneur', 'Kling Omni 2', ['TikTok/Reels'], 'done', null, null, 'lunas', null, 169, null, 'sudah', '150'],
            ['agensi', 'ai_preneur', 'Etira', ['Video'], 'done', null, null, 'lunas', 2_000_000, null, null, 'sudah', '200'],
            ['agensi', 'ai_preneur', 'Etira 2', ['Video'], 'done', '2026-03-11', '2026-04-29', 'lunas', 2_000_000, null, null, 'sudah', '200'],
            ['endorse', 'fk', 'Trae Solo', ['YouTube'], 'done', null, null, 'lunas', 8_000_000, null, null, 'sudah', '325'],
            ['endorse', 'fk', 'Seedance JIVE Sammie', ['YouTube'], 'done', '2026-04-21', null, 'lunas', null, 500, null, 'done', 'Handle Uli'],
            ['endorse', 'ai_preneur', 'TapNow', ['TikTok/Reels'], 'done', null, '2026-03-31', 'lunas', null, 299, null, 'sudah', '200'],
            ['endorse', 'ai_preneur', 'Dreamina CapCut', ['TikTok/Reels'], 'done', null, null, 'lunas', null, 180, null, 'sudah', '200'],
            ['endorse', 'fk', 'Pippit AI', ['TikTok/Reels'], 'done', '2026-06-19', '2026-06-19', 'lunas', 8_000_000, null, null, 'done', 'Handle Uli'],
            ['endorse', 'ai_preneur', 'Terabox Luar', ['TikTok/Reels'], 'done', null, '2026-04-23', 'lunas', null, 200, null, 'done', 'Handle Uli'],
            ['agensi', 'ai_preneur', 'SESE Video Ads', ['Video'], 'done', null, '2026-04-02', 'lunas', null, 99, null, 'done', 'Handle Uli'],
            ['endorse', 'fk', 'Terabox Indo', ['TikTok/Reels'], 'done', '2026-04-20', '2026-04-23', 'lunas', 3_000_000, null, null, 'sudah', '200'],
            ['endorse', 'fk', 'Zenva AI', ['TikTok/Reels'], 'done', '2026-04-21', '2026-04-29', 'lunas', 2_200_000, null, null, 'sudah', '150'],
            ['endorse', 'fk', 'Qwen AI', ['TikTok/Reels'], 'done', '2026-05-25', '2026-05-27', 'lunas', null, 318, null, 'done', 'Handle Uli'],
            ['endorse', 'fk', 'Everpro', ['TikTok/Reels'], 'done', '2026-05-07', '2026-05-08', 'lunas', 2_750_000, null, null, 'sudah', '150'],
            ['agensi', 'ai_preneur', 'Etira TVC', ['Video'], 'done', null, null, 'lunas', 1_300_000, null, null, 'sudah', '130k'],
            ['endorse', 'ai_preneur', 'Creatify', ['TikTok/Reels'], 'done', '2026-05-07', '2026-05-12', 'lunas', null, 150, null, 'sudah', '500K'],
            ['endorse', 'ai_preneur', 'Prism AI', ['TikTok/Reels'], 'done', '2026-05-15', '2026-05-25', 'lunas', null, 200, null, 'sudah', '50k'],
            ['endorse', 'fk', 'CBI visit jakarta', ['TikTok/Reels'], 'done', '2026-05-08', '2026-05-08', 'lunas', 4_900_000, null, null, 'done', 'Handle Uli'],
            ['endorse', 'fk', 'Everpro 2', ['TikTok/Reels'], 'done', '2026-05-28', '2026-05-28', 'lunas', 2_750_000, null, null, 'sudah', '50k'],
            ['endorse', 'fk', 'Halo AI', ['YouTube'], 'done', '2026-05-20', '2026-05-20', 'lunas', 7_500_000, null, null, 'sudah', '75k'],
            ['endorse', 'fk', 'Lumina', ['TikTok/Reels'], 'editing', null, '2026-06-19', 'lunas', null, 250, null, 'sudah', '50k'],
            ['endorse', 'fk', 'Halo AI Linkedin', ['TikTok/Reels'], 'done', '2026-05-22', '2026-05-25', 'lunas', 1_750_000, null, null, 'sudah', '50k'],
            ['endorse', 'fk', 'Emergent AI', ['TikTok/Reels'], 'done', '2026-05-29', '2026-06-06', 'belum', null, 233, null, 'sudah', '50k,50k'],
            ['endorse', 'fk', 'Capcut (Pippit)', ['TikTok/Reels'], 'done', '2026-05-26', null, 'lunas', 2_900_000, null, null, 'done', 'Handle uli'],
            ['endorse', 'fk', 'GAMSGO', ['YouTube'], 'done', null, '2026-06-19', 'lunas', null, 300, null, 'done', 'Handle uli'],
            // Tambahan baris 39–48 sheet
            ['endorse', 'fk', 'Visit Telkom', ['TikTok/Reels'], 'done', '2026-06-04', null, 'lunas', 5_500_000, null, null, 'belum', null],
            ['endorse', 'fk', 'BytePlus Seedance API', ['YouTube'], 'done', null, '2026-06-25', 'belum', null, 400, null, 'sudah', '75k'],
            ['endorse', 'fk', 'Lumina Coding Plan', ['TikTok/Reels'], 'done', '2026-06-30', null, 'belum', null, 250, null, 'belum', '50k'],
            ['endorse', 'fk', 'Agres ID', ['TikTok/Reels'], 'editing', null, null, 'belum', 5_000_000, null, null, 'belum', null],
            ['endorse', 'fk', 'Hostinger Claude', ['YouTube'], 'script', null, null, 'belum', null, 899, null, 'belum', null],
            ['agensi', 'ai_preneur', 'Agensi Lomba Polisi', ['Video'], 'done', null, null, 'lunas', 2_500_000, null, null, 'sudah', null],
            ['endorse', 'fk', 'Webshare (marija)', ['TikTok/Reels'], 'script', null, null, 'belum', null, 299, null, 'belum', null],
            ['endorse', 'fk', 'Skywork (charlote)', ['YouTube'], 'script', null, null, 'belum', null, 600, 'Posting: mid juli/30 juli', 'belum', null],
            // Coaching (baris 47–48)
            ['coaching', 'fk', 'Albert Siahaan (1on1 Coaching)', [], 'done', null, null, 'lunas', 9_900_000, null, null, 'belum', null],
            ['coaching', 'ai_preneur', 'Camilia Chippo (1on1 Coaching)', [], 'done', null, null, 'lunas', null, 1_900, null, 'belum', null],
        ];

        foreach ($rows as $r) {
            $pipeline = Pipeline::create([
                'category' => $r[0],
                'account' => $r[1],
                'endorse' => $r[2],
                'coaching' => $r[0] === 'coaching' ? $r[2] : null,
                'speaker' => $r[0] === 'speaker' ? $r[2] : null,
                'progress' => $r[4],
                'tanggal_posting' => $r[5],
                'tanggal_payment' => $r[6],
                'payment_status' => $r[7],
                'amount_idr' => $r[8],
                'amount_usd' => $r[9],
                'notes' => $r[10],
                'ke_gilang' => $r[11],
                'catatan' => $r[12],
            ]);
            $ids = array_map(fn ($name) => $o[$name], $r[3]);
            $pipeline->outputs()->sync($ids);
        }
    }
}
