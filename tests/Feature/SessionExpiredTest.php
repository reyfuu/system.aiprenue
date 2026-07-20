<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sesi habis harus melempar user ke login, bukan layar buntu "Page Expired".
 *
 * Catatan: middleware VerifyCsrfToken sengaja melewati request saat tes berjalan,
 * jadi kegagalan CSRF asli tak bisa dipicu di sini. Kita lempar exception yang
 * PERSIS sama dari sebuah route dadakan — yang diuji adalah handler-nya.
 */
class SessionExpiredTest extends TestCase
{
    use RefreshDatabase;

    private function routeYangKedaluwarsa(): void
    {
        Route::middleware('web')->post('/__tes-sesi-habis', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });
    }

    public function test_419_dialihkan_ke_login_dengan_pesan(): void
    {
        $this->routeYangKedaluwarsa();

        $response = $this->post('/__tes-sesi-habis');

        // 303 wajib: Inertia hanya mengikuti redirect setelah POST bila status 303.
        $response->assertStatus(303);
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Sesi kamu sudah habis. Silakan masuk lagi.');
    }

    public function test_request_json_tetap_dapat_419_bukan_redirect(): void
    {
        $this->routeYangKedaluwarsa();

        // Endpoint fetch() (drag kartu dll) harus tetap lihat kegagalan sebagai
        // kegagalan — kalau diredirect, fetch resolve 200 & error lolos diam-diam.
        $this->postJson('/__tes-sesi-habis')->assertStatus(419);
    }
}
