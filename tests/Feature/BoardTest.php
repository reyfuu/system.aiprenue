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

    public function test_buat_board_kanban_dapat_daftar_task_default(): void
    {
        $this->actingAs($this->user())->post('/boards', ['name' => 'Board Uji'])
            ->assertSessionHasNoErrors();

        $board = Category::where('key', 'board_uji')->first();
        $this->assertNotNull($board);
        $this->assertSame('kanban', $board->type);   // default bila type tak dikirim
        $this->assertSame(
            ['todo', 'progress', 'done'],
            BoardColumn::where('board_key', 'board_uji')->orderBy('position')->pluck('key')->all()
        );
    }

    /** Sales cuma boleh punya SATU board (`sales`) — pembeda deal di sana adalah
     *  `jenis`, bukan board terpisah. Tombolnya sudah hilang dari Vue, tapi itu tak
     *  cukup: request langsung harus ikut ditolak. */
    public function test_board_pipeline_kedua_tak_bisa_dibuat_walau_type_dipaksa(): void
    {
        $this->actingAs($this->user())->post('/boards', ['name' => 'Sales B', 'type' => 'pipeline'])
            ->assertSessionHasNoErrors();

        // terbuat, tapi sebagai kanban — `type` dari request diabaikan
        $this->assertSame('kanban', Category::where('key', 'sales_b')->value('type'));
        $this->assertSame(
            ['todo', 'progress', 'done'],
            BoardColumn::where('board_key', 'sales_b')->orderBy('position')->pluck('key')->all()
        );

        // board pipeline tetap satu-satunya: `sales`
        $this->assertSame(['sales'], Category::where('type', 'pipeline')->pluck('key')->all());
    }

    /** Tanpa board sales, menu Sales Pipeline mati 404. Penjagaan "board terakhir"
     *  tak menolong — ia menghitung SEMUA board, jadi sales tetap bisa dihapus
     *  selama masih ada board kanban lain. */
    public function test_board_sales_tak_bisa_dihapus(): void
    {
        $owner = $this->user();
        // sales kosong & masih ada board kanban lain → dua penjagaan lama sama-sama lolos
        Pipeline::where('category', 'sales')->delete();
        $this->assertGreaterThan(1, Category::count());

        $this->actingAs($owner)->delete('/boards/sales');

        $this->assertNotNull(Category::where('key', 'sales')->first(), 'board sales harus tetap ada');
        $this->actingAs($owner)->get('/pipelines')->assertOk();   // menu Sales tetap hidup
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

    public function test_board_todolist_permanen_walau_kosong(): void
    {
        $this->actingAs($this->user())->delete('/boards/todolist');

        $this->assertNotNull(Category::where('key', 'todolist')->first());
    }

    public function test_board_proyek_lain_yang_kosong_bisa_dihapus(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->post('/boards', ['name' => 'Proyek Sementara']);
        $this->actingAs($owner)->delete('/boards/proyek_sementara');

        $this->assertNull(Category::where('key', 'proyek_sementara')->first());
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

    public function test_admin_bisa_crud_kartu_kanban(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/pipelines', [
            'category' => 'todolist',
            'endorse' => 'Kartu admin',
            'progress' => 'todo',
            'account' => 'fk',
            'payment_status' => 'belum',
        ])->assertSessionHasNoErrors();

        $card = Pipeline::firstWhere('endorse', 'Kartu admin');
        $this->assertNotNull($card);

        $this->actingAs($admin)->put('/pipelines/'.$card->id, [
            'category' => 'todolist',
            'endorse' => 'Kartu admin diperbarui',
            'progress' => 'doing',
            'account' => 'fk',
            'payment_status' => 'belum',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Kartu admin diperbarui', $card->fresh()->endorse);

        $this->actingAs($admin)->delete('/pipelines/'.$card->id)->assertRedirect();
        $this->assertSoftDeleted('pipelines', ['id' => $card->id]);
    }

    public function test_nama_board_wajib(): void
    {
        $this->actingAs($this->user())->post('/boards', ['name' => ''])->assertSessionHasErrors('name');
    }
}
