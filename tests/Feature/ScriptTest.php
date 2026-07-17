<?php

namespace Tests\Feature;

use App\Models\Script;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Modul Script + pintu masuk agen Daily Script Rave (POST /api/scripts). */
class ScriptTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-uji-rahasia-panjang';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.script_agent.token', self::TOKEN);
    }

    private function user(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'brand'         => 'raveloux',
            'generated_for' => '2026-07-18',
            'drive_link'    => 'https://docs.google.com/document/d/abc123',
            'scripts'       => [
                ['title' => 'Hook kebaya modern', 'body' => "Baris 1\nBaris 2"],
                ['title' => 'Behind the scenes', 'body' => 'Isi naskah kedua'],
            ],
        ], $o);
    }

    private function kirim(array $payload = [], ?string $token = self::TOKEN)
    {
        return $this->withHeaders($token ? ['Authorization' => 'Bearer '.$token] : [])
            ->postJson('/api/scripts', $payload ?: $this->payload());
    }

    // ---- Pintu masuk agen ----

    public function test_agen_bisa_mengirim_paket_naskah(): void
    {
        $this->kirim()->assertCreated()->assertJson(['ok' => true, 'jumlah' => 2]);

        $this->assertSame(2, Script::count());
        $s = Script::first();
        $this->assertSame('raveloux', $s->brand);
        $this->assertSame('Hook kebaya modern', $s->title);
        $this->assertSame("Baris 1\nBaris 2", $s->body, 'baris baru dlm naskah harus utuh');
        $this->assertSame('2026-07-18', $s->generated_for->toDateString());
        $this->assertSame('https://docs.google.com/document/d/abc123', $s->drive_link);
    }

    /** Gerbangnya cuma token — kalau ini bocor, siapa pun bisa mengisi DB. */
    public function test_tanpa_token_ditolak(): void
    {
        $this->kirim([], null)->assertUnauthorized();
        $this->kirim([], 'token-ngawur')->assertUnauthorized();

        $this->assertSame(0, Script::count());
    }

    /** Lupa mengisi SCRIPT_AGENT_TOKEN jangan malah membuka endpointnya. */
    public function test_token_kosong_di_config_menolak_semua(): void
    {
        config()->set('services.script_agent.token', null);

        $this->kirim([], null)->assertUnauthorized();
        $this->kirim([], '')->assertUnauthorized();

        $this->assertSame(0, Script::count());
    }

    /** Workflow GitHub Actions bisa di-rerun manual. Tanpa ganti-paket, sekali
     *  rerun = naskah kembar untuk hari yang sama. */
    public function test_kirim_ulang_mengganti_paket_bukan_menambah(): void
    {
        $this->kirim()->assertCreated();
        $this->assertSame(2, Script::count());

        $this->kirim($this->payload(['scripts' => [
            ['title' => 'Naskah revisi', 'body' => 'Isi baru'],
        ]]))->assertCreated();

        $this->assertSame(1, Script::count(), 'paket lama harus diganti, bukan ditumpuk');
        $this->assertSame('Naskah revisi', Script::first()->title);
    }

    /** Ganti-paket cuma boleh menyentuh brand + tanggal yang sama. */
    public function test_ganti_paket_tak_menyentuh_brand_atau_tanggal_lain(): void
    {
        $this->kirim($this->payload(['brand' => 'raveloux', 'generated_for' => '2026-07-18']));
        $this->kirim($this->payload(['brand' => 'rave_tailor', 'generated_for' => '2026-07-18']));
        $this->kirim($this->payload(['brand' => 'raveloux', 'generated_for' => '2026-07-19']));
        $this->assertSame(6, Script::count());

        // kirim ulang raveloux 18 Jul → cuma 2 naskah itu yang tergantikan
        $this->kirim($this->payload(['scripts' => [['title' => 'Baru', 'body' => 'x']]]));

        $this->assertSame(5, Script::count());
        $this->assertSame(1, Script::where('brand', 'raveloux')->whereDate('generated_for', '2026-07-18')->count());
        $this->assertSame(2, Script::where('brand', 'rave_tailor')->count());
        $this->assertSame(2, Script::where('brand', 'raveloux')->whereDate('generated_for', '2026-07-19')->count());
    }

    public function test_brand_di_luar_daftar_ditolak(): void
    {
        $this->kirim($this->payload(['brand' => 'ngawur']))->assertStatus(422);
        $this->assertSame(0, Script::count());
    }

    public function test_paket_kosong_ditolak(): void
    {
        $this->kirim($this->payload(['scripts' => []]))->assertStatus(422);
        $this->kirim($this->payload(['scripts' => [['title' => '', 'body' => 'x']]]))->assertStatus(422);
        $this->assertSame(0, Script::count());
    }

    /** Insert gagal di tengah jangan meninggalkan paket lama yang sudah terhapus. */
    public function test_paket_lama_utuh_bila_kiriman_baru_ditolak(): void
    {
        $this->kirim()->assertCreated();

        $this->kirim($this->payload(['scripts' => [['title' => 'x', 'body' => '']]]))->assertStatus(422);

        $this->assertSame(2, Script::count(), 'kiriman ditolak → paket lama harus tetap ada');
    }

    // ---- Halaman ----

    public function test_galeri_menampilkan_jumlah_dan_paket_terbaru(): void
    {
        $this->kirim($this->payload(['generated_for' => '2026-07-18']));
        $this->kirim($this->payload(['generated_for' => '2026-07-25']));

        $this->actingAs($this->user())->get('/script')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Script')
                ->where('brands.0.key', 'raveloux')
                ->where('brands.0.count', 4)
                ->where('brands.0.latest', '25 Jul 2026')     // paket terbaru, bukan yg pertama
                ->where('brands.1.count', 0)                  // brand tanpa naskah tetap tampil
                ->where('brands.1.latest', null)
            );
    }

    public function test_halaman_brand_mengelompokkan_per_paket(): void
    {
        $this->kirim($this->payload(['generated_for' => '2026-07-18']));
        $this->kirim($this->payload(['generated_for' => '2026-07-25']));

        $this->actingAs($this->user())->get('/script/raveloux')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ScriptBrand')
                ->where('brand.name', 'Raveloux')
                ->has('packs', 2)
                ->where('packs.0.date', '2026-07-25')   // terbaru dulu
                ->where('packs.0.label', '25 Jul 2026')
                ->has('packs.0.items', 2)
                ->where('packs.1.date', '2026-07-18')
            );
    }

    public function test_brand_tak_dikenal_di_halaman_menjadi_404(): void
    {
        $this->actingAs($this->user())->get('/script/ngawur')->assertNotFound();
    }

    public function test_cari_menyaring_judul_dan_isi(): void
    {
        $this->kirim();

        $this->actingAs($this->user())->get('/script/raveloux?search=kebaya')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('packs', 1)
                ->has('packs.0.items', 1)
                ->where('packs.0.items.0.title', 'Hook kebaya modern')
            );

        // cocok lewat isi naskah, bukan judul
        $this->actingAs($this->user())->get('/script/raveloux?search=naskah kedua')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('packs.0.items.0.title', 'Behind the scenes'));
    }

    public function test_manajemen_boleh_hapus_naskah(): void
    {
        $this->kirim();
        $s = Script::first();

        $this->actingAs($this->user('manager'))->delete('/script/'.$s->id)->assertSessionHasNoErrors();

        $this->assertNull($s->fresh());
    }

    /** Staff tak punya menu `script` sama sekali sejak akses dipersempit. */
    public function test_staff_tak_punya_akses_script(): void
    {
        $this->kirim();
        $staff = $this->user('staff');

        $this->actingAs($staff)->get('/script')->assertForbidden();
        $this->actingAs($staff)->get('/script/raveloux')->assertForbidden();
        $this->actingAs($staff)->delete('/script/'.Script::first()->id)->assertForbidden();

        $this->assertSame(2, Script::count());
    }

    public function test_tamu_ditolak(): void
    {
        $this->get('/script')->assertRedirect('/login');
    }
}
