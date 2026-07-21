<?php

namespace Tests\Feature;

use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Tracking eksekutif harus selalu diturunkan dari kartu Kanban. */
class TrackingTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function card(array $override = []): Pipeline
    {
        return Pipeline::create(array_merge([
            'category' => 'todolist',
            'endorse' => 'Task tracking',
            'progress' => 'todo',
            'account' => 'fk',
            'payment_status' => 'belum',
        ], $override));
    }

    public function test_owner_melihat_ringkasan_progress_dari_kanban(): void
    {
        $this->card(['progress' => 'done']);
        $this->card(['progress' => 'doing', 'deadline' => now()->subDay()]);

        $this->actingAs($this->user('owner'))->get('/tracking')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tracking')
                ->where('auth.user.menus.tracking', true)
                ->where('summary.cards', 2)
                ->where('summary.done', 1)
                ->where('summary.overdue', 1)
                ->where('tracking.0.percent', 50)
                ->where('tracking.0.health', 'red')
            );
    }

    public function test_manager_bisa_melihat_tracking(): void
    {
        $this->actingAs($this->user('manager'))->get('/tracking')->assertOk();
    }

    public function test_admin_it_dan_staff_tidak_bisa_melihat_tracking(): void
    {
        foreach (['admin', 'it', 'staff'] as $role) {
            $this->actingAs($this->user($role))->get('/tracking')->assertForbidden();
        }
    }
}
