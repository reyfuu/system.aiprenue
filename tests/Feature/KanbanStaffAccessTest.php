<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Staff boleh CRUD KARTU di papan Kanban (bukan Sales), tapi TIDAK boleh
 *  menyentuh struktur (kolom/board). Route kartu dipakai bersama Sales & Kanban,
 *  jadi gerbangnya per-TIPE board (User::canManageBoard). Regresi yang dicegah:
 *  1) edit judul kartu kanban dulu 403 (menusFor jatuh ke ['pipeline']);
 *  2) staff sempat bisa mengutak-atik kartu/kolom Sales lewat route bersama. */
class KanbanStaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('usd_idr_rate', 16250.5);   // hindari HTTP kurs saat render board
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    private function kanbanCard(): Pipeline
    {
        return Pipeline::create([
            'category' => 'todolist', 'account' => 'fk', 'endorse' => 'Task lama',
            'progress' => 'todo', 'payment_status' => 'belum',
        ]);
    }

    private function cardPayload(array $o = []): array
    {
        return array_merge([
            'category' => 'todolist', 'account' => 'fk', 'endorse' => 'Task Baru',
            'progress' => 'todo', 'payment_status' => 'belum',
        ], $o);
    }

    public function test_staff_boleh_buat_kartu_di_kanban(): void
    {
        $this->actingAs($this->staff())
            ->post('/pipelines', $this->cardPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame('Task Baru', Pipeline::firstWhere('category', 'todolist')?->endorse);
    }

    public function test_staff_boleh_edit_judul_kartu_kanban(): void
    {
        $card = $this->kanbanCard();

        $this->actingAs($this->staff())
            ->put('/pipelines/'.$card->id, $this->cardPayload(['endorse' => 'Judul Diedit']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Judul Diedit', $card->fresh()->endorse);
    }

    public function test_staff_boleh_arsip_dan_hapus_kartu_kanban(): void
    {
        $card = $this->kanbanCard();
        $staff = $this->staff();

        $this->actingAs($staff)->patch('/pipelines/'.$card->id.'/archive')->assertRedirect();
        $this->assertNotNull($card->fresh()->archived_at);

        $this->actingAs($staff)->delete('/pipelines/'.$card->id)->assertRedirect();
        $this->assertNull(Pipeline::find($card->id));
    }

    public function test_staff_boleh_geser_kartu_kanban_tapi_tidak_geser_kolom(): void
    {
        $card = $this->kanbanCard();
        $staff = $this->staff();

        // Kartu (pipelines.reorder) → boleh di board kanban
        $this->actingAs($staff)
            ->patchJson('/pipelines/reorder', ['progress' => 'doing', 'ids' => [$card->id]])
            ->assertOk();
        $this->assertSame('doing', $card->fresh()->progress);

        // Kolom (columns.reorder) = struktur → tetap tertutup
        $ids = BoardColumn::where('board_key', 'todolist')->pluck('id')->all();
        $this->actingAs($staff)
            ->patchJson('/columns/reorder', ['ids' => array_reverse($ids)])
            ->assertForbidden();
    }

    public function test_staff_tetap_tak_boleh_kartu_sales(): void
    {
        $sales = Pipeline::create([
            'category' => 'sales', 'account' => 'fk', 'endorse' => 'Deal',
            'progress' => 'lead', 'payment_status' => 'belum',
        ]);
        $staff = $this->staff();
        $salesPayload = [
            'category' => 'sales', 'account' => 'fk', 'endorse' => 'X',
            'progress' => 'lead', 'payment_status' => 'belum',
        ];

        $this->actingAs($staff)->post('/pipelines', $salesPayload)->assertForbidden();
        $this->actingAs($staff)->put('/pipelines/'.$sales->id, $salesPayload)->assertForbidden();
        $this->actingAs($staff)->delete('/pipelines/'.$sales->id)->assertForbidden();

        $this->assertSame('Deal', $sales->fresh()->endorse);
    }

    public function test_staff_tak_boleh_kelola_struktur_kanban(): void
    {
        $col = BoardColumn::where('board_key', 'todolist')->first();
        $staff = $this->staff();

        $this->actingAs($staff)->post('/columns', ['board_key' => 'todolist', 'name' => 'X'])->assertForbidden();
        $this->actingAs($staff)->put('/columns/'.$col->id, ['name' => 'X'])->assertForbidden();
        $this->actingAs($staff)->post('/boards', ['name' => 'Board Baru'])->assertForbidden();
    }
}
