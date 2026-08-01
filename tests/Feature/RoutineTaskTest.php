<?php

namespace Tests\Feature;

use App\Models\RoutineTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutineTaskTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    public function test_toggle_menandai_selesai_hari_ini_lalu_melepas(): void
    {
        $user = $this->user();
        $task = RoutineTask::create(['user_id' => $user->id, 'title' => 'Cek Kanban', 'position' => 0]);

        $this->actingAs($user)->patch("/routine-tasks/{$task->id}/toggle");
        $this->assertTrue($task->fresh()->completed_on->isToday());

        $this->actingAs($user)->patch("/routine-tasks/{$task->id}/toggle");
        $this->assertNull($task->fresh()->completed_on);
    }

    public function test_penyelesaian_kemarin_bukan_selesai_hari_ini(): void
    {
        // Inti "reset harian": completed_on kemarin → done_today = false tanpa cron.
        $user = $this->user();
        $task = RoutineTask::create([
            'user_id' => $user->id, 'title' => 'Cek Insight',
            'position' => 0, 'completed_on' => today()->subDay(),
        ]);

        $this->assertFalse($task->completed_on->isToday());
    }

    public function test_tidak_bisa_menyentuh_rutinitas_orang_lain(): void
    {
        $owner = $this->user();
        $task = RoutineTask::create(['user_id' => $owner->id, 'title' => 'Punya A', 'position' => 0]);

        $this->actingAs($this->user())->patch("/routine-tasks/{$task->id}/toggle")->assertForbidden();
    }
}
