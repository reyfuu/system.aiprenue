<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Statistik OKR tak boleh berubah saat kartu di-drag antar kolom.
 *  completed_at hanya distempel oleh aksi eksplisit (updateDone, sync task). */
class CompletedAtFreezeTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    public function test_drag_kartu_ke_kolom_selesai_tidak_stempel_completed_at(): void
    {
        $user = $this->owner();

        $card = Pipeline::create([
            'category' => 'todolist', 'account' => 'fk', 'endorse' => 'Kartu Uji',
            'progress' => 'todo', 'payment_status' => 'belum',
        ]);
        $this->assertNull($card->completed_at);

        // Drag ke kolom terakhir (done = posisi 2 pada board todolist)
        $this->actingAs($user)->patch('/pipelines/reorder', [
            'progress' => 'done',
            'ids' => [$card->id],
        ])->assertOk();

        $card->refresh();
        $this->assertSame('done', $card->progress);
        $this->assertNull($card->completed_at, 'Drag TIDAK boleh stempel completed_at');
    }

    public function test_drag_kartu_keluar_kolom_selesai_tidak_cabut_completed_at(): void
    {
        $user = $this->owner();

        $card = Pipeline::create([
            'category' => 'todolist', 'account' => 'fk', 'endorse' => 'Kartu Uji 2',
            'progress' => 'done', 'payment_status' => 'belum',
            'completed_at' => now(),
        ]);
        $this->assertNotNull($card->completed_at);

        // Drag dari done ke todo
        $this->actingAs($user)->patch('/pipelines/reorder', [
            'progress' => 'todo',
            'ids' => [$card->id],
        ])->assertOk();

        $card->refresh();
        $this->assertSame('todo', $card->progress);
        $this->assertNotNull($card->completed_at, 'Drag TIDAK boleh cabut completed_at');
    }

    public function test_update_done_tetap_stempel_completed_at(): void
    {
        $user = $this->owner();

        $card = Pipeline::create([
            'category' => 'todolist', 'account' => 'fk', 'endorse' => 'Kartu Uji 3',
            'progress' => 'todo', 'payment_status' => 'belum',
        ]);
        $this->assertNull($card->completed_at);

        // Tombol "Selesai" — aksi eksplisit, bukan drag
        $this->actingAs($user)->patch('/pipelines/'.$card->id.'/done', [
            'done' => true,
        ])->assertOk();

        $card->refresh();
        $this->assertNotNull($card->completed_at, 'updateDone(true) TETAP stempel completed_at');
    }
}
