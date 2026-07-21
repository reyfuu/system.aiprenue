<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Respons Inertia tak boleh disimpan browser.
 *
 * Gejala kalau pagar ini jebol: user melihat JSON mentah memenuhi layar alih-alih
 * halaman — bodi JSON yang tersimpan disajikan ulang untuk navigasi dokumen.
 */
class InertiaCacheHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_respons_inertia_tidak_boleh_disimpan(): void
    {
        $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'x'])->get('/login');

        // `no-cache` saja TIDAK cukup — artinya "revalidasi", bukan "jangan simpan".
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
    }

    /**
     * Respons 409 (versi aset tak cocok) adalah justru jalur yang memicu masalah,
     * dan Inertia melewatkan `Vary` di situ. Versi sengaja dibuat ngawur agar
     * jalur inilah yang kena.
     */
    public function test_respons_409_versi_tak_cocok_tetap_punya_vary(): void
    {
        $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'ngawur'])->get('/login');

        $r->assertStatus(409);
        $this->assertSame('X-Inertia', $r->headers->get('Vary'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
    }

    /** Navigasi dokumen biasa tak ikut disentuh — cukup `no-cache` bawaan session. */
    public function test_dokumen_biasa_tidak_diubah(): void
    {
        $r = $this->get('/login');

        $r->assertOk();
        $this->assertStringNotContainsString('no-store', (string) $r->headers->get('Cache-Control'));
    }

    /** SSR wajib mati secara default: produksi = shared hosting tanpa Node. */
    public function test_ssr_mati_secara_default(): void
    {
        $this->assertFalse(config('inertia.ssr.enabled'));
    }
}
