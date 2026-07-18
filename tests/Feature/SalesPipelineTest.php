<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
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
            ->patchJson('/pipelines/reorder', ['progress' => 'nego', 'ids' => [$card->id]])
            ->assertOk();

        $this->assertSame('nego', $card->fresh()->progress);
    }

    /** Inti keluhannya: menggeser kartu naik/turun di dalam kolom yang sama dulu
     *  tak tersimpan sama sekali — di layar pindah, lalu balik begitu dimuat
     *  ulang, karena tak ada kolom urutan & klien membuang event 'moved'. */
    public function test_geser_urutan_dalam_kolom_tersimpan(): void
    {
        [$a, $b, $c] = [$this->card('lead'), $this->card('lead'), $this->card('lead')];

        $this->actingAs($this->user('manager'))
            ->patchJson('/pipelines/reorder', ['progress' => 'lead', 'ids' => [$c->id, $a->id, $b->id]])
            ->assertOk();

        $urut = Pipeline::where('progress', 'lead')->orderBy('position')->orderBy('id')->pluck('id')->all();
        $this->assertSame([$c->id, $a->id, $b->id], $urut, 'urutan hasil drag harus bertahan');
    }

    /** Urutan wajib datang dari `position`, bukan kebetulan urutan id. */
    public function test_kanban_menampilkan_kartu_sesuai_urutan_tersimpan(): void
    {
        [$a, $b] = [$this->card('lead'), $this->card('lead')];

        $this->actingAs($this->user('manager'))
            ->patchJson('/pipelines/reorder', ['progress' => 'lead', 'ids' => [$b->id, $a->id]])->assertOk();

        $this->actingAs($this->user('manager'))->get('/pipelines')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.lead.0.id', $b->id)   // yg digeser ke atas tampil duluan
                ->where('board.lead.1.id', $a->id)
            );
    }

    /** Kolom (wadah kartu) bisa digeser utk atur urutan — dulu urutannya mati,
     *  cuma bisa diubah dgn hapus & bikin ulang kolom. */
    public function test_geser_urutan_kolom_tersimpan(): void
    {
        $ids = BoardColumn::forBoard('sales')->pluck('id')->all();  // lead, kontak, nego, closing, deal
        $dibalik = array_reverse($ids);

        $this->actingAs($this->user('manager'))
            ->patchJson('/columns/reorder', ['ids' => $dibalik])
            ->assertOk();

        $this->assertSame($dibalik, BoardColumn::forBoard('sales')->pluck('id')->all(), 'urutan kolom hasil drag harus bertahan');

        // Urutan wajib sampai ke layar, bukan cuma ke DB.
        $this->actingAs($this->user('manager'))->get('/pipelines')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('columns.0.key', 'deal'));
    }

    /** Kiriman sebagian = position kembar = urutan kolom acak. Ditolak di server,
     *  bukan cuma diandalkan ke klien yang selalu mengirim semua. */
    public function test_urutan_kolom_sebagian_ditolak(): void
    {
        $ids = BoardColumn::forBoard('sales')->pluck('id')->all();

        $this->actingAs($this->user('manager'))
            ->patchJson('/columns/reorder', ['ids' => [$ids[1], $ids[0]]])   // 2 dari 5
            ->assertStatus(422);

        $this->assertSame($ids, BoardColumn::forBoard('sales')->pluck('id')->all(), 'urutan tak boleh berubah');
    }

    /** Id kolom unik global → tanpa pagar, satu kiriman bisa menata board lain.
     *  Board kedua = 'todolist': sesudah migrasi 15 Juli cuma tinggal sales & todolist
     *  (endorse dkk lebur jadi `jenis` kartu, kolomnya ikut terhapus).
     *
     *  Bentuk kiriman dipilih HATI-HATI, sudah diuji-mutasi: 2 kolom todolist +
     *  1 kolom sales = 3 id. Controller menentukan board dari kolom ber-id TERKECIL,
     *  dan id todolist selalu di bawah sales (migrasinya lebih awal) → board terbaca
     *  'todolist' yang jumlah kolomnya 3, jadi pagar KELENGKAPAN meloloskan kiriman ini
     *  dan satu-satunya yang bisa menolak adalah pagar lintas-board.
     *
     *  Versi lugu (2 id, atau 4 sales + 1 todolist) tetap hijau walau pagar lintas-board
     *  dihapus — yang menolak pagar kelengkapan, bukan yang sedang diuji.
     */
    public function test_urutan_kolom_lintas_board_ditolak(): void
    {
        $sales    = BoardColumn::forBoard('sales')->pluck('id')->all();
        $todolist = BoardColumn::where('board_key', 'todolist')->orderBy('id')->pluck('id')->all();

        $this->assertCount(3, $todolist, 'prasyarat: board todolist wajib punya 3 kolom');

        $ids = $todolist;
        array_pop($ids);
        $ids[] = $sales[0];                       // 2 todolist + 1 sales, tetap 3 id

        $this->actingAs($this->user('manager'))
            ->patchJson('/columns/reorder', ['ids' => $ids])
            ->assertStatus(422);

        $this->assertSame($sales, BoardColumn::forBoard('sales')->pluck('id')->all(), 'board sales tak boleh ikut tertata');
        $this->assertSame($todolist, BoardColumn::forBoard('todolist')->pluck('id')->all(), 'board todolist tak boleh ikut tertata');
    }

    /** staff view-only: tombolnya memang disembunyikan di Vue, tapi request langsung
     *  harus tetap ditolak — gerbangnya dari prefix `columns.` di EnsureMenuAccess. */
    public function test_staff_tak_bisa_geser_urutan_kolom(): void
    {
        $ids = BoardColumn::forBoard('sales')->pluck('id')->all();

        $this->actingAs($this->user('staff'))
            ->patchJson('/columns/reorder', ['ids' => array_reverse($ids)])
            ->assertStatus(403);

        $this->assertSame($ids, BoardColumn::forBoard('sales')->pluck('id')->all());
    }

    public function test_stage_di_luar_board_ditolak(): void
    {
        $card = $this->card('lead');

        $this->actingAs($this->user('manager'))
            ->patchJson('/pipelines/reorder', ['progress' => 'editing', 'ids' => [$card->id]]) // kolom board lain
            ->assertStatus(422);

        $this->assertSame('lead', $card->fresh()->progress);
    }

    /** Key kolom tak unik antar board (dua board bisa sama-sama punya 'script'),
     *  jadi board WAJIB diambil dari kartunya. Kalau diambil dari request, kartu
     *  bisa dilempar ke kolom milik board lain. */
    public function test_kartu_dari_board_berbeda_ditolak(): void
    {
        $sales = $this->card('lead');
        $lain  = Pipeline::create(['category' => 'endorse', 'progress' => 'editing', 'endorse' => 'X']);

        $this->actingAs($this->user('manager'))
            ->patchJson('/pipelines/reorder', ['progress' => 'lead', 'ids' => [$sales->id, $lain->id]])
            ->assertStatus(422);

        $this->assertSame('editing', $lain->fresh()->progress);
    }

    public function test_id_tak_ada_ditolak(): void
    {
        $this->actingAs($this->user('manager'))
            ->patchJson('/pipelines/reorder', ['progress' => 'lead', 'ids' => [999999]])
            ->assertNotFound();
    }

    /** Admin: boleh KELOLA (CRUD) sales/kanban/mindmap, tapi tertutup dari
     *  order/pembukuan/user. Beda dari staff yang cuma view-only. */
    public function test_admin_bisa_kelola_sales_kanban_mindmap_saja(): void
    {
        $admin = $this->user('admin');
        $card = $this->card('lead');

        // sales (=pipeline) & kanban terbuka + BOLEH mutasi
        $this->actingAs($admin)->get('/pipelines')->assertOk();
        $this->actingAs($admin)->get('/pipelines/kanban')->assertOk();
        $this->actingAs($admin)
            ->patchJson('/pipelines/reorder', ['progress' => 'nego', 'ids' => [$card->id]])
            ->assertOk();
        $this->assertSame('nego', $card->fresh()->progress);

        // mindmap terbuka
        $this->actingAs($admin)->get('/mindmaps')->assertOk();

        // di luar jatahnya → 403
        $this->actingAs($admin)->get('/orders')->assertForbidden();
        $this->actingAs($admin)->get('/users')->assertForbidden();
        $this->actingAs($admin)->get('/pembukuan')->assertForbidden();
    }

    /** Kontak lead (WA/Gmail/DM IG) tersimpan lewat form & sampai ke prop kartu. */
    public function test_kontak_lead_tersimpan(): void
    {
        $this->actingAs($this->user('admin'))->post('/pipelines', [
            'category' => 'sales', 'account' => 'fk', 'endorse' => 'Deal kontak',
            'progress' => 'lead', 'payment_status' => 'belum',
            'kontak_wa' => '0812-3456', 'kontak_gmail' => 'lead@gmail.com', 'kontak_ig' => '@akun',
        ])->assertSessionHasNoErrors();

        $kartu = Pipeline::firstWhere('endorse', 'Deal kontak');
        $this->assertSame('0812-3456', $kartu->kontak_wa);
        $this->assertSame('lead@gmail.com', $kartu->kontak_gmail);
        $this->assertSame('@akun', $kartu->kontak_ig);

        // sampai ke layar (prop kartu)
        $this->actingAs($this->user('admin'))->get('/pipelines')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('board.lead.0.kontak_wa', '0812-3456'));
    }

    /** Staff cuma boleh Kanban & Mindmap — Sales Pipeline tertutup sama sekali,
     *  termasuk mutasinya (yang lewat route bersama Kanban). */
    public function test_staff_tak_boleh_lihat_maupun_ubah_sales_pipeline(): void
    {
        $card = $this->card('lead');
        $staff = $this->user('staff');

        $this->actingAs($staff)->get('/pipelines')->assertForbidden();
        $this->actingAs($staff)->patchJson('/pipelines/reorder', ['progress' => 'nego', 'ids' => [$card->id]])->assertForbidden();
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
