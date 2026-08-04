<?php

namespace Tests\Feature;

use App\Models\InsightContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Filter di halaman Insight harus konsisten untuk platform dan tipe konten. */
class InsightControllerTest extends TestCase
{
    use RefreshDatabase;

    private function buatKonten(string $platform, string $contentId, ?string $type = null): void
    {
        InsightContent::create([
            'platform'    => $platform,
            'content_id'  => $contentId,
            'judul'       => 'Judul '.$contentId,
            'content_type' => $type,
            'views'       => 100,
            'published_at' => now(),
        ]);
    }

    public function test_filter_platform_bisa_dibatasi_content_type(): void
    {
        $this->buatKonten('youtube', 'yt_short', 'short');
        $this->buatKonten('youtube', 'yt_video', 'video');
        $this->buatKonten('instagram', 'ig_reel', 'reel');

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/insight?platform=youtube&content_type=short')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('aktif', 'youtube')
                ->where('aktifTipe', 'short')
                ->where('contentTypes.short', 'YouTube Short')
                ->where('konten.0.tipe', 'short')
                ->where('ringkasan.konten', 1)
            );
    }

    public function test_filter_platform_tanpa_content_type_muat_semua_tipenya(): void
    {
        $this->buatKonten('youtube', 'yt_short', 'short');
        $this->buatKonten('youtube', 'yt_video', 'video');
        $this->buatKonten('instagram', 'ig_reel', 'reel');

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/insight?platform=youtube')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('aktif', 'youtube')
                ->where('aktifTipe', 'semua')
                ->has('konten', 2)
            );
    }

    public function test_filter_content_type_invalid_kembali_ke_semua(): void
    {
        $this->buatKonten('youtube', 'yt_short', 'short');

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/insight?content_type=unknown')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('aktifTipe', 'semua')
                ->has('konten', 1)
            );
    }
}
