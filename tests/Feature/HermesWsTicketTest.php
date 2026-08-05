<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class HermesWsTicketTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_ws_ticket_fallback_ke_http_bila_url_belum_diset(): void
    {
        Config::set('services.hermes_agent.url', '');
        Config::set('services.hermes_agent.token', 'token-ada');

        $this->actingAs($this->user())
            ->getJson('/hermes/ws-ticket')
            ->assertOk()
            ->assertJson([
                'ok' => false,
                'ticket' => null,
                'fallback_http' => true,
                'websocket_available' => false,
            ]);
    }

    public function test_ws_ticket_fallback_ke_http_bila_token_belum_diset(): void
    {
        Config::set('services.hermes_agent.url', 'https://hermes.aipreneur.co.id');
        Config::set('services.hermes_agent.token', '');

        $this->actingAs($this->user())
            ->getJson('/hermes/ws-ticket')
            ->assertOk()
            ->assertJson([
                'ok' => false,
                'ticket' => null,
                'fallback_http' => true,
                'websocket_available' => false,
            ]);
    }

    public function test_staff_tidak_boleh_minta_tiket_ws_hermes(): void
    {
        Config::set('services.hermes_agent.url', 'https://hermes.aipreneur.co.id');
        Config::set('services.hermes_agent.token', 'token-ada');

        $this->actingAs($this->user('staff'))
            ->getJson('/hermes/ws-ticket')
            ->assertForbidden();
    }
}
