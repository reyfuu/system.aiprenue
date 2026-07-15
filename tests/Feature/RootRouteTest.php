<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Route '/' = pengalih, bukan halaman. Menggantikan ExampleTest bawaan Laravel
 *  yang menuntut '/' balas 200 — asumsi yang tak pernah benar di app ini. */
class RootRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_user_login_dialihkan_ke_halaman_awalnya(): void
    {
        foreach (['owner', 'manager', 'it', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/')
                ->assertRedirect(route('dashboard'));   // semua peran punya menu dashboard
        }
    }

    public function test_halaman_terproteksi_menolak_tamu(): void
    {
        foreach (['/dashboard', '/pipelines', '/pipelines/kanban', '/orders', '/users', '/pembukuan'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }
}
