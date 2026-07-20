<?php

namespace Tests\Feature;

use App\Models\Mindmap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Template mindmap: kerangka siap pakai saat membuat mindmap baru. */
class MindmapTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_galeri_mengirim_daftar_template(): void
    {
        $this->actingAs($this->user())->get('/mindmaps')->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->has('templates')
                ->where('templates.0.key', 'kosong'));   // kosong selalu pertama
    }

    /** Template kosong = perilaku lama: data null, frontend pakai MindElixir.new(). */
    public function test_template_kosong_tak_mengisi_data(): void
    {
        $this->actingAs($this->user())->post('/mindmaps', ['template' => 'kosong'])->assertRedirect();

        $this->assertNull(Mindmap::latest('id')->first()->data);
    }

    /** Yang penting bukan cuma "tersimpan", tapi bentuknya DIPAHAMI mind-elixir:
     *  wajib ada nodeData + root true + children. Salah bentuk = kanvas kosong
     *  padahal datanya ada. */
    public function test_template_berisi_menghasilkan_struktur_mind_elixir(): void
    {
        $this->actingAs($this->user())->post('/mindmaps', ['template' => 'swot'])->assertRedirect();

        $data = Mindmap::latest('id')->first()->data;

        $this->assertIsArray($data);
        $this->assertArrayHasKey('nodeData', $data);
        $this->assertTrue($data['nodeData']['root']);
        $this->assertSame('SWOT Brand', $data['nodeData']['topic']);

        $cabang = collect($data['nodeData']['children']);
        $this->assertSame(['Kekuatan', 'Kelemahan', 'Peluang', 'Ancaman'], $cabang->pluck('topic')->all());
        $this->assertNotEmpty($cabang->first()['children'], 'tiap cabang harus punya anak');
    }

    /** Id node wajib unik — mind-elixir memakainya untuk menautkan node, id kembar
     *  bikin node saling menimpa saat diedit. */
    public function test_semua_id_node_unik(): void
    {
        $data = Mindmap::dataDariTemplate('alur_produksi', 'Alur Produksi');

        $ids = [];
        $kumpulkan = function ($node) use (&$kumpulkan, &$ids) {
            $ids[] = $node['id'];
            foreach ($node['children'] ?? [] as $anak) {
                $kumpulkan($anak);
            }
        };
        $kumpulkan($data['nodeData']);

        $this->assertGreaterThan(5, count($ids), 'template harus punya banyak node');
        $this->assertSame(count($ids), count(array_unique($ids)), 'id node tak boleh kembar');
    }

    /** Cabang dibagi kiri-kanan; kalau semua satu sisi, petanya berat sebelah. */
    public function test_cabang_terbagi_dua_sisi(): void
    {
        $data = Mindmap::dataDariTemplate('kampanye', 'Rencana Kampanye');
        $arah = collect($data['nodeData']['children'])->pluck('direction')->unique()->values();

        $this->assertEqualsCanonicalizing([0, 1], $arah->all(), 'harus ada cabang kiri DAN kanan');
    }

    public function test_template_ngawur_ditolak(): void
    {
        $this->actingAs($this->user())
            ->post('/mindmaps', ['template' => 'template-karangan'])
            ->assertSessionHasErrors('template');

        $this->assertSame(0, Mindmap::count());
    }

    /** Judul dari template dipakai kalau user tak mengisi judul sendiri. */
    public function test_judul_ikut_nama_template(): void
    {
        $this->actingAs($this->user())->post('/mindmaps', ['template' => 'brainstorm_konten'])->assertRedirect();

        $this->assertSame('Brainstorm Konten', Mindmap::latest('id')->first()->title);
    }
}
