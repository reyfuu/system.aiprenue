<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CRUD kolom board (tabel `board_columns`). */
class ColumnTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_tambah_kolom_masuk_ke_posisi_terakhir(): void
    {
        $this->actingAs($this->user())
            ->post('/columns', ['board_key' => 'sales', 'name' => 'Follow Up'])
            ->assertSessionHasNoErrors();

        $kolom = BoardColumn::where('board_key', 'sales')->orderBy('position')->pluck('key')->all();
        $this->assertSame(['lead', 'kontak', 'nego', 'closing', 'deal', 'follow_up'], $kolom);
    }

    public function test_key_kolom_dibuat_unik_per_board(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->post('/columns', ['board_key' => 'sales', 'name' => 'Follow Up']);
        $this->actingAs($owner)->post('/columns', ['board_key' => 'sales', 'name' => 'Follow Up']);

        $this->assertSame(
            ['follow_up', 'follow_up_2'],
            BoardColumn::where('board_key', 'sales')->whereIn('key', ['follow_up', 'follow_up_2'])->pluck('key')->all()
        );
    }

    public function test_ubah_nama_kolom(): void
    {
        $col = BoardColumn::where('board_key', 'sales')->where('key', 'nego')->first();

        $this->actingAs($this->user())->put('/columns/'.$col->id, ['name' => 'Negosiasi'])
            ->assertSessionHasNoErrors();

        // key TIDAK ikut berubah — pipelines.progress menunjuk ke key ini
        $this->assertSame('nego', $col->fresh()->key);
        $this->assertSame('Negosiasi', $col->fresh()->name);
    }

    public function test_kolom_berisi_kartu_tak_bisa_dihapus(): void
    {
        Pipeline::create([
            'category' => 'sales', 'account' => 'fk', 'endorse' => 'Deal',
            'progress' => 'nego', 'payment_status' => 'belum',
        ]);
        $col = BoardColumn::where('board_key', 'sales')->where('key', 'nego')->first();

        $this->actingAs($this->user())->delete('/columns/'.$col->id);

        $this->assertNotNull($col->fresh(), 'kolom berisi kartu tak boleh hilang');
    }

    public function test_kolom_kosong_bisa_dihapus(): void
    {
        $col = BoardColumn::where('board_key', 'sales')->where('key', 'nego')->first();

        $this->actingAs($this->user())->delete('/columns/'.$col->id);

        $this->assertNull($col->fresh());
    }

    public function test_kolom_terakhir_tak_bisa_dihapus(): void
    {
        $owner = $this->user();
        BoardColumn::where('board_key', 'sales')->where('key', '!=', 'lead')->delete();
        $col = BoardColumn::where('board_key', 'sales')->first();

        $this->actingAs($owner)->delete('/columns/'.$col->id);

        $this->assertNotNull($col->fresh(), 'minimal satu kolom harus tersisa');
    }

    public function test_staff_tak_boleh_crud_kolom(): void
    {
        $staff = $this->user('staff');
        $col = BoardColumn::where('board_key', 'sales')->where('key', 'nego')->first();

        $this->actingAs($staff)->post('/columns', ['board_key' => 'sales', 'name' => 'X'])->assertForbidden();
        $this->actingAs($staff)->put('/columns/'.$col->id, ['name' => 'X'])->assertForbidden();
        $this->actingAs($staff)->delete('/columns/'.$col->id)->assertForbidden();

        $this->assertSame('Nego', $col->fresh()->name);
    }

    public function test_nama_kolom_wajib(): void
    {
        $this->actingAs($this->user())
            ->post('/columns', ['board_key' => 'sales', 'name' => ''])
            ->assertSessionHasErrors('name');
    }
}
