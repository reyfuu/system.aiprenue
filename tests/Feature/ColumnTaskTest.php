<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\ColumnTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColumnTaskTest extends TestCase
{
    use RefreshDatabase;

    private function column(): BoardColumn
    {
        return BoardColumn::create(['board_key' => 'kanban', 'key' => 'script', 'name' => 'Script', 'position' => 0]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_owner_manager_bisa_delegasi_staff_tak_bisa(): void
    {
        $col = $this->column();
        $staff = $this->user('staff');
        $payload = ['board_column_id' => $col->id, 'title' => 'Cek insight', 'assigned_to' => $staff->id];

        $this->actingAs($this->user('owner'))->post('/column-tasks', $payload)->assertRedirect();
        $this->assertDatabaseHas('column_tasks', ['title' => 'Cek insight', 'assigned_to' => $staff->id]);

        // Staff tak boleh mendelegasikan.
        $this->actingAs($staff)->post('/column-tasks', $payload)->assertForbidden();
    }

    public function test_assignee_bisa_centang_dan_menetap(): void
    {
        $col = $this->column();
        $staff = $this->user('staff');
        $task = ColumnTask::create(['board_column_id' => $col->id, 'assigned_to' => $staff->id, 'title' => 'X', 'position' => 0]);

        $this->actingAs($staff)->patch("/column-tasks/{$task->id}/toggle");
        $this->assertNotNull($task->fresh()->completed_at); // menetap: tetap terisi

        $this->actingAs($staff)->patch("/column-tasks/{$task->id}/toggle");
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_manager_boleh_centang_item_staff_orang_lain_tidak(): void
    {
        $col = $this->column();
        $task = ColumnTask::create(['board_column_id' => $col->id, 'assigned_to' => $this->user('staff')->id, 'title' => 'X', 'position' => 0]);

        $this->actingAs($this->user('manager'))->patch("/column-tasks/{$task->id}/toggle")->assertRedirect();
        // Staff lain (bukan yang ditugasi, bukan manajer) → 403.
        $this->actingAs($this->user('staff'))->patch("/column-tasks/{$task->id}/toggle")->assertForbidden();
    }

    public function test_hapus_hanya_owner_manager(): void
    {
        $col = $this->column();
        $staff = $this->user('staff');
        $task = ColumnTask::create(['board_column_id' => $col->id, 'assigned_to' => $staff->id, 'title' => 'X', 'position' => 0]);

        $this->actingAs($staff)->delete("/column-tasks/{$task->id}")->assertForbidden();
        $this->actingAs($this->user('owner'))->delete("/column-tasks/{$task->id}")->assertRedirect();
        $this->assertDatabaseMissing('column_tasks', ['id' => $task->id]);
    }
}
