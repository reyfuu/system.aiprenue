<?php

namespace Tests\Feature;

use App\Models\InsightContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gerbang & idempotensi POST /api/insights. Tiga jalur yang paling gampang jebol:
 * token belum dikonfigurasi (503), token salah (401), dan kiriman ulang yang
 * seharusnya meng-UPDATE — bukan menggandakan — baris.
 */
class InsightIngestTest extends TestCase
{
    use RefreshDatabase;

    private array $payload = [
        'contents' => [[
            'platform' => 'youtube', 'content_id' => 'vid_1',
            'judul' => 'Halo Dunia', 'views' => 100, 'likes' => 10,
        ]],
        'accounts' => [[
            'platform' => 'youtube', 'akun' => 'UC_x', 'tanggal' => '2026-07-20', 'followers' => 500,
        ]],
    ];

    public function test_token_belum_dikonfigurasi_balas_503(): void
    {
        config(['services.insight_agent.token' => '']);

        $this->postJson('/api/insights', $this->payload)->assertStatus(503);
    }

    public function test_token_salah_balas_401(): void
    {
        config(['services.insight_agent.token' => 'benar']);

        $this->withToken('salah')->postJson('/api/insights', $this->payload)->assertStatus(401);
    }

    public function test_token_benar_menyimpan_dan_idempoten(): void
    {
        config(['services.insight_agent.token' => 'rahasia']);

        $this->withToken('rahasia')->postJson('/api/insights', $this->payload)
            ->assertStatus(201)
            ->assertJson(['ok' => true, 'contents' => 1, 'accounts' => 1]);

        // Kirim ulang konten yang sama dgn angka baru → UPDATE, bukan baris kembar.
        $ulang = $this->payload;
        $ulang['contents'][0]['views'] = 250;
        $this->withToken('rahasia')->postJson('/api/insights', $ulang)->assertStatus(201);

        $this->assertSame(1, InsightContent::where('content_id', 'vid_1')->count());
        $this->assertSame(250, InsightContent::where('content_id', 'vid_1')->value('views'));
    }
}
