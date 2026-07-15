<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Category;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CRUD board (tabel `categories`). */
class BoardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_buat_board_kanban_dapat_kolom_produksi(): void
    {
        $this->actingAs($this->user())->post('/boards', ['name' => 'Board Uji'])
            ->assertSessionHasNoErrors();

        $board = Category::where('key', 'board_uji')->first();
        $this->assertNotNull($board);
        $this->assertSame('kanban', $board->type);   // default bila type tak dikirim
        $this->assertSame(
            ['script', 'editing', 'progress', 'pending', 'done'],
            BoardColumn::where('board_key', 'board_uji')->orderBy('position')->pluck('key')->all()
        );
    }

    public function test_buat_board_pipeline_dapat_stage_sales(): void
    {
        $this->actingAs($this->user())->post('/boards', ['name' => 'Sales B', 'type' => 'pipeline'])
            ->assertSessionHasNoErrors();

        $this->assertSame('pipeline', Category::where('key', 'sales_b')->value('type'));
        $this->assertSame(
            ['lead', 'kontak', 'nego', 'closing', 'deal'],
            BoardColumn::where('board_key', 'sales_b')->orderBy('position')->pluck('key')->all()
        );
    }

    public function test_key_board_dibuat_unik(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->post('/boards', ['name' => 'Duplikat']);
        $this->actingAs($owner)->post('/boards', ['name' => 'Duplikat']);

        $this->assertSame(['duplikat', 'duplikat_2'], Category::whereIn('key', ['duplikat', 'duplikat_2'])->pluck('key')->all());
    }

    public function test_ubah_nama_board(): void
    {
        $this->actingAs($this->user())->put('/boards/todolist', ['name' => 'Todo Baru'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Todo Baru', Category::where('key', 'todolist')->value('name'));
    }

    public function test_board_berisi_kartu_tak_bisa_dihapus(): void
    {
        Pipeline::create([
            'category' => 'todolist', 'account' => 'fk', 'endorse' => 'Task',
            'progress' => 'todo', 'payment_status' => 'belum',
        ]);

        $this->actingAs($this->user())->delete('/boards/todolist');

        $this->assertNotNull(Category::where('key', 'todolist')->first(), 'board berisi kartu tak boleh hilang');
    }

    public function test_board_kosong_bisa_dihapus(): void
    {
        $this->actingAs($this->user())->delete('/boards/todolist');

        $this->assertNull(Category::where('key', 'todolist')->first());
    }

    public function test_board_terakhir_tak_bisa_dihapus(): void
    {
        $owner = $this->user();
        Category::where('key', '!=', 'sales')->delete();   // sisakan satu

        $this->actingAs($owner)->delete('/boards/sales');

        $this->assertSame(1, Category::count(), 'minimal satu board harus tersisa');
    }

    public function test_staff_tak_boleh_crud_board(): void
    {
        $staff = $this->user('staff');

        $this->actingAs($staff)->post('/boards', ['name' => 'X'])->assertForbidden();
        $this->actingAs($staff)->put('/boards/todolist', ['name' => 'X'])->assertForbidden();
        $this->actingAs($staff)->delete('/boards/todolist')->assertForbidden();

        $this->assertSame('Todolist', Category::where('key', 'todolist')->value('name'));
    }

    public function test_nama_board_wajib(): void
    {
        $this->actingAs($this->user())->post('/boards', ['name' => ''])->assertSessionHasErrors('name');
    }
}
