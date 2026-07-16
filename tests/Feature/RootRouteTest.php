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
        // staff tak punya dashboard → jatuh ke menu pertama yg boleh dilihatnya
        $tujuan = [
            'owner'   => 'dashboard',
            'manager' => 'dashboard',
            'it'      => 'dashboard',
            'staff'   => 'pipelines.kanban',
        ];

        foreach ($tujuan as $role => $route) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/')
                ->assertRedirect(route($route));
        }
    }

    public function test_halaman_terproteksi_menolak_tamu(): void
    {
        foreach (['/dashboard', '/pipelines', '/pipelines/kanban', '/orders', '/users', '/pembukuan'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }
}
