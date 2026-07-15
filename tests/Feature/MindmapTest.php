<?php

namespace Tests\Feature;

use App\Models\Mindmap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CRUD mindmap. Semua peran punya menu `mindmap`, tapi buat/ubah/hapus
 *  seharusnya manajemen saja — Mindmap/Index.vue menyembunyikan tombolnya
 *  di balik `canManage`, jadi route-nya wajib menegakkan hal yang sama. */
class MindmapTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_buat_mindmap_memakai_judul_default_bila_kosong(): void
    {
        $owner = $this->user();

        $this->actingAs($owner)->post('/mindmaps', [])->assertSessionHasNoErrors();

        $m = Mindmap::first();
        $this->assertSame('Mindmap Baru', $m->title);
        $this->assertSame($owner->id, $m->user_id);   // pemilik = user login
        $this->assertNull($m->data);                   // struktur default diisi frontend
    }

    public function test_buat_mindmap_dengan_judul(): void
    {
        $this->actingAs($this->user())->post('/mindmaps', ['title' => 'Riset Konten'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Riset Konten', Mindmap::first()->title);
    }

    public function test_simpan_struktur_node(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->post('/mindmaps', ['title' => 'A']);
        $m = Mindmap::first();

        $this->actingAs($owner)->putJson('/mindmaps/'.$m->id, [
            'title' => 'A Diubah',
            'data'  => ['nodeData' => ['id' => 'root', 'topic' => 'Root']],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame('A Diubah', $m->fresh()->title);
        $this->assertSame(['nodeData' => ['id' => 'root', 'topic' => 'Root']], $m->fresh()->data);
    }

    public function test_data_wajib_array_saat_simpan(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->post('/mindmaps', ['title' => 'A']);

        $this->actingAs($owner)->putJson('/mindmaps/'.Mindmap::first()->id, ['title' => 'A', 'data' => 'bukan-array'])
            ->assertStatus(422);
    }

    public function test_hapus_mindmap(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->post('/mindmaps', ['title' => 'A']);

        $this->actingAs($owner)->delete('/mindmaps/'.Mindmap::first()->id)->assertSessionHasNoErrors();

        $this->assertSame(0, Mindmap::count());
    }

    public function test_staff_boleh_melihat_galeri_dan_editor(): void
    {
        $this->actingAs($this->user())->post('/mindmaps', ['title' => 'A']);
        $staff = $this->user('staff');

        $this->actingAs($staff)->get('/mindmaps')->assertOk();
        $this->actingAs($staff)->get('/mindmaps/'.Mindmap::first()->id)->assertOk();
    }

    public function test_staff_tak_boleh_membuat_mengubah_atau_menghapus_mindmap(): void
    {
        $this->actingAs($this->user())->post('/mindmaps', ['title' => 'Punya owner']);
        $m = Mindmap::first();
        $staff = $this->user('staff');

        $this->actingAs($staff)->post('/mindmaps', ['title' => 'Selundupan'])->assertForbidden();
        $this->actingAs($staff)->putJson('/mindmaps/'.$m->id, ['title' => 'Dibajak', 'data' => ['x' => 1]])->assertForbidden();
        $this->actingAs($staff)->delete('/mindmaps/'.$m->id)->assertForbidden();

        $this->assertSame(1, Mindmap::count());
        $this->assertSame('Punya owner', $m->fresh()->title);
    }
}
