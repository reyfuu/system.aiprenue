<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Sales Pipeline = board tipe `pipeline` yang dirender Kanban.vue.
 *  Cek: halaman kebuka sbg board, drag menyimpan stage, staff tak bisa mengubah. */
class SalesPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Nilai deal dijumlahkan pakai kurs USD→IDR (ExchangeRate). Cache di test = array,
        // jadi tanpa ini tiap request benar-benar memanggil open.er-api.com → lambat & flaky.
        // Isi cache-nya = Cache::remember langsung hit, tak ada HTTP sama sekali.
        // (Jangan pakai Http::preventStrayRequests(): ikut memblokir ping Inertia SSR → 500.)
        \Illuminate\Support\Facades\Cache::put('usd_idr_rate', 16250.5);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function card(string $progress = 'lead'): Pipeline
    {
        return Pipeline::create([
            'category' => 'sales', 'account' => 'fk', 'endorse' => 'Deal uji',
            'progress' => $progress, 'payment_status' => 'belum',
        ]);
    }

    public function test_pipeline_dirender_sebagai_board_sales_dengan_stage_sales(): void
    {
        $this->actingAs($this->user('owner'))
            ->get('/pipelines')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kanban')                       // bukan tabel lagi
                ->where('category', 'sales')                // menu Pipeline mendarat di Sales
                ->where('baseUrl', '/pipelines')
                ->where('showGallery', false)               // Sales Pipeline tak punya galeri
                ->where('boardType', 'pipeline')            // board baru dari sini ikut tipe pipeline
                ->where('rate', 16250.5)                    // kurs > 0, kalau tidak deal USD dihitung nol
                ->where('columns.0.key', 'lead')
                ->where('columns.4.key', 'deal')
            );
    }

    /** endorse/coaching/agensi/speaker berhenti jadi board — turun jadi `jenis` kartu. */
    public function test_board_pipeline_lama_sudah_lebur_jadi_satu(): void
    {
        $this->assertSame(['sales'], Category::where('type', 'pipeline')->pluck('key')->all());
        $this->assertSame(0, Category::whereIn('key', ['endorse', 'coaching', 'agensi', 'speaker'])->count());

        // ?category ke board yg sudah tiada → jatuh ke sales, bukan error
        $this->actingAs($this->user('owner'))
            ->get('/pipelines?category=endorse')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('category', 'sales'));
    }

    public function test_kartu_board_lama_pindah_ke_sales_dengan_jenis_utuh(): void
    {
        // Kartu dibuat sebelum merge di-rollback/ulang tak bisa disimulasikan di sini;
        // yg dijaga: jenis tersimpan & tampil sbg label kartu.
        $card = Pipeline::create([
            'category' => 'sales', 'jenis' => 'coaching_perusahaan', 'account' => 'fk',
            'endorse' => 'Deal coaching', 'progress' => 'lead', 'payment_status' => 'belum',
        ]);

        $this->actingAs($this->user('owner'))->get('/pipelines')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.lead.0.id', $card->id)
                ->where('board.lead.0.jenis', 'coaching_perusahaan')
                ->where('board.lead.0.jenis_label', 'Coaching Perusahaan')
            );
    }

    public function test_jenis_di_luar_daftar_ditolak(): void
    {
        $this->actingAs($this->user('manager'))
            ->post('/pipelines', [
                'category' => 'sales', 'jenis' => 'ngawur', 'account' => 'fk',
                'endorse' => 'x', 'progress' => 'lead', 'payment_status' => 'belum',
            ])
            ->assertSessionHasErrors('jenis');

        $this->assertSame(0, Pipeline::count());
    }

    /** Modal buat & edit kini satu form, jadi kartu baru dikirim lengkap dengan
     *  detailnya sekali POST — bukan bikin dulu lalu dibuka lagi untuk diisi. */
    public function test_kartu_baru_dibuat_lengkap_dengan_detailnya_sekali_kirim(): void
    {
        $pj = $this->user('staff');
        $output = \App\Models\Output::create(['name' => 'Reels']);

        $this->actingAs($this->user('owner'))->post('/pipelines', [
            'category' => 'sales', 'account' => 'fk', 'endorse' => 'Deal lengkap',
            'progress' => 'nego', 'jenis' => 'coaching_1on1', 'payment_status' => 'dp',
            'description' => 'Detail deal', 'deadline' => '2026-09-01',
            'assigned_to' => $pj->id, 'amount_idr' => 5_000_000, 'amount_usd' => 250,
            'link' => 'https://example.com/video', 'notes' => 'Catatan',
            'outputs' => [$output->id],
            'labels' => [['name' => 'Prioritas', 'color' => 'bg-red-500']],
        ])->assertSessionHasNoErrors();

        $kartu = Pipeline::firstWhere('endorse', 'Deal lengkap');
        $this->assertNotNull($kartu, 'kartu harus terbuat');
        $this->assertSame('nego', $kartu->progress);
        $this->assertSame('coaching_1on1', $kartu->jenis);
        $this->assertSame('Detail deal', $kartu->description);
        $this->assertSame('dp', $kartu->payment_status);
        $this->assertSame($pj->id, $kartu->assigned_to);
        $this->assertEquals(5_000_000, $kartu->amount_idr);
        $this->assertEquals(250, $kartu->amount_usd);
        $this->assertSame('2026-09-01', $kartu->deadline?->toDateString());
        $this->assertSame('Catatan', $kartu->notes);
        $this->assertSame([['name' => 'Prioritas', 'color' => 'bg-red-500']], $kartu->labels);
        $this->assertSame([$output->id], $kartu->outputs->pluck('id')->all());
    }

    /** Output 'Video' & 'Foto' datang dari migrasi (bukan seeder — seeder dipagari
     *  di produksi). Checkbox di modal kartu ikut isi tabel ini, jadi tak ada kode UI. */
    public function test_output_video_dan_foto_tersedia_dan_bisa_dicentang(): void
    {
        $video = \App\Models\Output::firstWhere('name', 'Video');
        $foto = \App\Models\Output::firstWhere('name', 'Foto');
        $this->assertNotNull($video, "output 'Video' harus ada dari migrasi");
        $this->assertNotNull($foto, "output 'Foto' harus ada dari migrasi");

        $this->actingAs($this->user('owner'))->post('/pipelines', [
            'category' => 'sales', 'account' => 'fk', 'endorse' => 'Deal berfoto',
            'progress' => 'lead', 'payment_status' => 'belum',
            'outputs' => [$video->id, $foto->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            ['Foto', 'Video'],
            Pipeline::firstWhere('endorse', 'Deal berfoto')->outputs->pluck('name')->sort()->values()->all()
        );
    }

    private function deal(string $jenis, string $progress = 'lead'): Pipeline
    {
        return Pipeline::create([
            'category' => 'sales', 'account' => 'fk', 'endorse' => 'Deal '.$jenis,
            'progress' => $progress, 'payment_status' => 'belum', 'jenis' => $jenis,
        ]);
    }

    /** Filter chip: bisa banyak jenis sekaligus — justru itu yang membedakannya dari
     *  dropdown board (yang cuma bisa satu) & bikin manager tak salah baca. */
    public function test_filter_jenis_bisa_lebih_dari_satu_sekaligus(): void
    {
        $this->deal('endorse');
        $this->deal('speaker');
        $this->deal('agensi');

        // NB: Inertia mengirim daftar kartu sbg Collection ke closure, bukan array.
        $judul = fn ($kartu) => collect($kartu)->pluck('endorse')->sort()->values()->all();

        // dua chip aktif → dua jenis itu saja
        $this->actingAs($this->user('owner'))->get('/pipelines?jenis[]=endorse&jenis[]=speaker')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('jenis', ['endorse', 'speaker'])
                ->where('board.lead', fn ($k) => $judul($k) === ['Deal endorse', 'Deal speaker'])
            );

        // tanpa chip → semua
        $this->actingAs($this->user('owner'))->get('/pipelines')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('jenis', [])
                ->where('board.lead', fn ($k) => count($k) === 3)
            );
    }

    /** ?jenis ngawur jangan bikin board kosong tanpa sebab — dibuang, tampilkan semua. */
    public function test_jenis_ngawur_di_filter_diabaikan_bukan_error(): void
    {
        $this->deal('endorse');

        $this->actingAs($this->user('owner'))->get('/pipelines?jenis[]=ngawur')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('jenis', [])
                ->where('board.lead', fn ($k) => count($k) === 1)
            );

        // campur valid + ngawur → yang ngawur dibuang, yang valid tetap jalan
        $this->actingAs($this->user('owner'))->get('/pipelines?jenis[]=ngawur&jenis[]=endorse')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('jenis', ['endorse']));
    }

    /** Angka di chip TIDAK boleh ikut filter — kalau ikut, semua chip lain jadi 0
     *  begitu satu chip dipilih, dan angkanya tak bisa lagi dipakai memilih. */
    public function test_angka_chip_tak_ikut_menyusut_saat_difilter(): void
    {
        $this->deal('endorse');
        $this->deal('endorse');
        $this->deal('speaker');

        $this->actingAs($this->user('owner'))->get('/pipelines?jenis[]=endorse')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('jenisCounts.endorse', 2)
                ->where('jenisCounts.speaker', 1)   // tetap 1 walau sedang disaring ke endorse
                ->where('board.lead', fn ($k) => count($k) === 2)
            );
    }

    /** "Estimasi board" = SELURUH board, jadi TIDAK boleh ikut menyusut saat chip
     *  dipilih. Kalau ikut, itu total tersaring — bukan yang diminta. */
    public function test_estimasi_board_tak_ikut_menyusut_saat_difilter(): void
    {
        Pipeline::create(['category' => 'sales', 'account' => 'fk', 'endorse' => 'A',
            'progress' => 'lead', 'payment_status' => 'belum', 'jenis' => 'endorse',
            'amount_idr' => 10_000_000]);
        Pipeline::create(['category' => 'sales', 'account' => 'fk', 'endorse' => 'B',
            'progress' => 'lead', 'payment_status' => 'belum', 'jenis' => 'speaker',
            'amount_idr' => 4_000_000]);

        $owner = $this->user('owner');

        $this->actingAs($owner)->get('/pipelines')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('boardTotal', 14_000_000));

        // chip endorse aktif → board tetap 14 jt, walau yang tampil cuma 10 jt
        $this->actingAs($owner)->get('/pipelines?jenis[]=endorse')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('boardTotal', 14_000_000)
                ->where('board.lead', fn ($k) => count($k) === 1)
            );
    }

    /** Nilai USD ikut dikonversi — kalau tidak, deal USD dihitung nol tanpa suara. */
    public function test_estimasi_board_mengonversi_usd_pakai_kurs(): void
    {
        Pipeline::create(['category' => 'sales', 'account' => 'fk', 'endorse' => 'USD',
            'progress' => 'lead', 'payment_status' => 'belum',
            'amount_idr' => 1_000_000, 'amount_usd' => 100]);

        // 1.000.000 + 100 × 16.250,5 = 2.625.050
        $this->actingAs($this->user('owner'))->get('/pipelines')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('boardTotal', 2_625_050));
    }

    /** Refactor renderBoard() dipakai bersama — pastikan modul Kanban tak ikut berubah. */
    public function test_kanban_masih_punya_galeri_dan_base_url_sendiri(): void
    {
        $owner = $this->user('owner');

        // tanpa ?category → galeri board
        $this->actingAs($owner)->get('/pipelines/kanban')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('BoardGallery'));

        // dengan ?category → board kanban (todolist tak tersentuh peleburan)
        $this->actingAs($owner)->get('/pipelines/kanban?category=todolist')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kanban')
                ->where('baseUrl', '/pipelines/kanban')
                ->where('showGallery', true)
                ->where('boardType', 'kanban')
            );
    }

    /** migrate:fresh --seed harus menghasilkan SATU board pipeline berstage sales.
     *  Jebakan: migrasi board_columns menyemai kolom produksi utk SEMUA kategori,
     *  dan seeder jalan SETELAH migrasi — gampang jadi 10 kolom / board kebangkitan. */
    public function test_seeder_memberi_satu_board_sales_saja(): void
    {
        $this->seed(\Database\Seeders\PipelineSeeder::class);

        $this->assertSame(['sales'], Category::where('type', 'pipeline')->pluck('key')->all());
        $this->assertSame(
            ['lead', 'kontak', 'nego', 'closing', 'deal'],
            \App\Models\BoardColumn::where('board_key', 'sales')->orderBy('position')->pluck('key')->all()
        );

        // Kartu dummy: semua di board sales, stage valid, jenis valid.
        // Stage/jenis ngawur = kartu tampil di kolom pertama tapi DB beda (desync diam-diam).
        $this->assertGreaterThan(0, Pipeline::count());
        $this->assertSame(0, Pipeline::where('category', '!=', 'sales')->count());
        $this->assertSame([], Pipeline::whereNotIn('progress', ['lead', 'kontak', 'nego', 'closing', 'deal'])->pluck('progress')->all());
        $this->assertSame([], Pipeline::whereNotIn('jenis', array_keys(Pipeline::JENIS))->pluck('jenis')->all());
    }

    public function test_drag_kartu_menyimpan_stage_baru(): void
    {
        $card = $this->card('lead');

        $this->actingAs($this->user('manager'))
            ->patchJson("/pipelines/{$card->id}/progress", ['progress' => 'nego'])
            ->assertOk();

        $this->assertSame('nego', $card->fresh()->progress);
    }

    public function test_stage_di_luar_board_ditolak(): void
    {
        $card = $this->card('lead');

        $this->actingAs($this->user('manager'))
            ->patchJson("/pipelines/{$card->id}/progress", ['progress' => 'editing']) // kolom board lain
            ->assertStatus(422);
    }

    /** Staff cuma boleh Kanban & Mindmap — Sales Pipeline tertutup sama sekali,
     *  termasuk mutasinya (yang lewat route bersama Kanban). */
    public function test_staff_tak_boleh_lihat_maupun_ubah_sales_pipeline(): void
    {
        $card = $this->card('lead');
        $staff = $this->user('staff');

        $this->actingAs($staff)->get('/pipelines')->assertForbidden();
        $this->actingAs($staff)->patchJson("/pipelines/{$card->id}/progress", ['progress' => 'nego'])->assertForbidden();
        // `done` dulu lolos cek canManage() — sekarang ikut tertutup
        $this->actingAs($staff)->patchJson("/pipelines/{$card->id}/done", ['done' => true])->assertForbidden();

        $this->assertSame('lead', $card->fresh()->progress);
        $this->assertFalse((bool) $card->fresh()->done);
    }

    public function test_laporan_pdf_sudah_dihapus(): void
    {
        // NB: cek route, bukan status HTTP — GET /pipelines/report kini cocok dgn
        // pola /pipelines/{pipeline} sehingga membalas 405, bukan 404.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('pipelines.report'));
    }
}
