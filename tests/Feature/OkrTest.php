<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\BoardQuarterTarget;
use App\Models\Category;
use App\Models\InsightAccount;
use App\Models\InsightContent;
use App\Models\KeyResult;
use App\Models\Objective;
use App\Models\Pipeline;
use App\Models\Transaction;
use App\Models\User;
use App\Support\OkrMetrics;
use App\Support\Quarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** OKR (perusahaan) + KPI board kuartalan. */
class OkrTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /** Board kanban + kolom bawaan, seperti yang dibuat BoardController. */
    private function board(string $key = 'proyek'): Category
    {
        $board = Category::create(['key' => $key, 'name' => ucfirst($key), 'type' => 'kanban']);
        foreach (['todo', 'progress', 'done'] as $i => $col) {
            BoardColumn::create(['board_key' => $key, 'key' => $col, 'name' => $col, 'color' => 'bg-slate-400', 'position' => $i]);
        }

        return $board;
    }

    private function objective(int $year = 2026, int $quarter = 3): Objective
    {
        return Objective::create(['year' => $year, 'quarter' => $quarter, 'title' => 'Objective uji']);
    }

    private function kartu(array $attr = []): Pipeline
    {
        return Pipeline::create(array_merge([
            'category' => 'proyek', 'endorse' => 'Task', 'account' => 'fk',
            'progress' => 'todo', 'payment_status' => 'belum',
        ], $attr));
    }

    // ---------------------------------------------------------------- akses

    public function test_owner_bisa_membuka_halaman_okr(): void
    {
        $this->actingAs($this->user())->get('/okr')->assertOk();
    }

    /** Staff tak punya menu 'okr' — halaman berisi omset, sejalan dgn pembukuan. */
    public function test_staff_ditolak_membuka_okr(): void
    {
        $this->actingAs($this->user('staff'))->get('/okr')->assertForbidden();
    }

    /** OKR dikunci owner+manager di canSee(). Peran 'it' punya akses penuh ke
     *  hampir semua menu, jadi ia justru kasus yang paling mudah bocor kalau
     *  penguncian itu hilang. */
    public function test_it_ditolak_membuka_okr(): void
    {
        $this->actingAs($this->user('it'))->get('/okr')->assertForbidden();
    }

    public function test_manager_bisa_membuka_okr(): void
    {
        $this->actingAs($this->user('manager'))->get('/okr')->assertOk();
    }

    /** KPI board TIDAK dikunci seperti OKR: isinya operasional papan, bukan
     *  keuangan, jadi 'it' & 'admin' tetap boleh. Ini inti pemisahan halaman. */
    public function test_kpi_board_boleh_dilihat_it_dan_admin(): void
    {
        $this->actingAs($this->user('it'))->get('/kpi')->assertOk();
        $this->actingAs($this->user('admin'))->get('/kpi')->assertOk();
    }

    /** Staff kini BOLEH membuka /kpi — tapi hanya untuk rapor dirinya sendiri.
     *  Tab Per Board tak dikirim sama sekali; lihat KpiOrangTest. */
    public function test_staff_boleh_membuka_kpi_tanpa_tab_board(): void
    {
        $this->actingAs($this->user('staff'))->get('/kpi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('board', null)
                ->where('scope', 'sendiri')
            );
    }

    /** Halaman KPI tak boleh membocorkan angka OKR — itu seluruh alasan
     *  keduanya dipisah. */
    public function test_halaman_kpi_tidak_memuat_data_okr(): void
    {
        $this->board();

        $this->actingAs($this->user('it'))->get('/kpi')
            ->assertInertia(fn ($page) => $page->component('Kpi')->missing('objectives'));
    }

    /** Menetapkan target = mutasi. Peran view-only harus tertolak walau
     *  request dikirim langsung, bukan cuma tombolnya disembunyikan di Vue. */
    public function test_staff_tak_bisa_membuat_objective(): void
    {
        $this->actingAs($this->user('staff'))->post('/okr/objectives', [
            'year' => 2026, 'quarter' => 3, 'title' => 'Diam-diam',
        ])->assertForbidden();

        $this->assertDatabaseCount('objectives', 0);
    }

    /** Route CRUD OKR yang baru ikut terjaring sbg mutasi — daftar-hitam di
     *  EnsureMenuAccess (semua okr.* kecuali okr.index), bukan daftar-putih
     *  per nama yang mudah terlupa saat route bertambah. */
    public function test_it_tak_bisa_menyentuh_route_okr_apa_pun(): void
    {
        $it = $this->user('it');

        $this->actingAs($it)->post('/okr/objectives', ['year' => 2026, 'quarter' => 3, 'title' => 'X'])->assertForbidden();
        $this->actingAs($it)->post('/okr/key-results', ['objective_id' => 1, 'title' => 'X', 'source' => 'manual', 'target' => 1, 'unit' => 'angka'])->assertForbidden();
        $this->actingAs($it)->post('/okr/salin', ['year' => 2026, 'quarter' => 3])->assertForbidden();
    }

    // ------------------------------------------------------------- target

    public function test_objective_dan_key_result_dibuat(): void
    {
        $owner = $this->user();

        $this->actingAs($owner)->post('/okr/objectives', [
            'year' => 2026, 'quarter' => 3, 'title' => 'Jadi rujukan konten AI',
        ])->assertSessionHasNoErrors();

        $objective = Objective::first();
        $this->assertSame($owner->id, $objective->created_by);

        $this->actingAs($owner)->post('/okr/key-results', [
            'objective_id' => $objective->id, 'title' => 'Total view',
            'source' => 'auto', 'metric' => 'view', 'target' => 750000, 'unit' => 'angka',
        ])->assertSessionHasNoErrors();

        // PJ default = owner, sesuai keputusan "sementara penanggung jawabnya owner dulu".
        $this->assertSame($owner->id, KeyResult::first()->owner_id);
    }

    /** Menghapus Objective ikut menghapus Key Result-nya — KR tanpa induk tak
     *  punya arti apa pun (cascadeOnDelete di skema). */
    public function test_hapus_objective_menghapus_key_result(): void
    {
        $o = $this->objective();
        KeyResult::create(['objective_id' => $o->id, 'title' => 'KR', 'source' => 'manual', 'target' => 10, 'unit' => 'angka']);

        $this->actingAs($this->user())->delete('/okr/objectives/'.$o->id)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('objectives', 0);
        $this->assertDatabaseCount('key_results', 0);
    }

    /** Angka otomatis yang bisa ditimpa tangan berhenti bisa dipercaya —
     *  ditolak terang-terangan, bukan diabaikan diam-diam. */
    public function test_key_result_otomatis_tak_bisa_ditimpa_manual(): void
    {
        $o = $this->objective();
        $kr = KeyResult::create(['objective_id' => $o->id, 'title' => 'View', 'source' => 'auto', 'metric' => 'view', 'target' => 100, 'unit' => 'angka']);

        $this->actingAs($this->user())
            ->patch('/okr/key-results/'.$kr->id.'/actual', ['actual_manual' => 999])
            ->assertSessionHasErrors('actual_manual');

        $this->assertNull($kr->fresh()->actual_manual);
    }

    public function test_key_result_manual_bisa_diperbarui_angkanya(): void
    {
        $o = $this->objective();
        $kr = KeyResult::create(['objective_id' => $o->id, 'title' => 'Klien baru', 'source' => 'manual', 'target' => 10, 'unit' => 'angka']);

        $this->actingAs($this->user())
            ->patch('/okr/key-results/'.$kr->id.'/actual', ['actual_manual' => 7])
            ->assertSessionHasNoErrors();

        $this->assertSame(70.0, $kr->fresh()->percent());
    }

    /**
     * Roll-up Objective: tiap KR dibatasi 100% SEBELUM dirata-rata.
     *
     *  Tanpa batas itu, KR 300% + KR 0% menghasilkan 150% dan Objective
     *  terbaca "tercapai" padahal separuhnya belum jalan sama sekali.
     */
    public function test_progress_objective_membatasi_key_result_di_100_persen(): void
    {
        $o = $this->objective();
        KeyResult::create(['objective_id' => $o->id, 'title' => 'Jauh melampaui', 'source' => 'manual', 'target' => 10, 'actual_manual' => 30, 'unit' => 'angka']);
        KeyResult::create(['objective_id' => $o->id, 'title' => 'Belum jalan', 'source' => 'manual', 'target' => 10, 'actual_manual' => 0, 'unit' => 'angka']);

        // (min(100,300) + 0) / 2 = 50, bukan (300 + 0) / 2 = 150.
        $this->assertSame(50.0, $o->fresh()->progress([]));
    }

    /** KR tanpa target diabaikan dari rata-rata, bukan dihitung 0:
     *  "belum ditetapkan" bukan "belum tercapai". */
    public function test_key_result_tanpa_target_tak_menyeret_progress(): void
    {
        $o = $this->objective();
        KeyResult::create(['objective_id' => $o->id, 'title' => 'Tercapai', 'source' => 'manual', 'target' => 10, 'actual_manual' => 10, 'unit' => 'angka']);
        KeyResult::create(['objective_id' => $o->id, 'title' => 'Belum bertarget', 'source' => 'manual', 'target' => 0, 'unit' => 'angka']);

        $this->assertSame(100.0, $o->fresh()->progress([]));
    }

    // ------------------------------------------- KR sumber 'kartu' & tautan

    /** KR bersumber 'kartu': realisasi = jumlah kartu tautan yang SELESAI
     *  (completed_at terisi), bukan sekadar jumlah kartu tertaut. */
    public function test_key_result_kartu_menghitung_kartu_tautan_yang_selesai(): void
    {
        $o = $this->objective();
        $kr = KeyResult::create(['objective_id' => $o->id, 'title' => 'Kolaborasi 5 kreator', 'source' => 'kartu', 'target' => 5, 'unit' => 'angka']);

        // 3 selesai (completed_at terisi), 2 masih berjalan.
        foreach ([true, true, true, false, false] as $i => $selesai) {
            $this->kartu([
                'category' => 'todolist', 'endorse' => "Langkah $i",
                'key_result_id' => $kr->id,
                'completed_at' => $selesai ? '2026-08-01 10:00:00' : null,
            ]);
        }

        $this->assertSame(3.0, $kr->fresh()->actual());
        $this->assertSame(60.0, $kr->fresh()->percent());
    }

    /** updateActual menolak source 'kartu' sama seperti 'auto': angkanya
     *  dihitung dari kartu, bukan diketik. */
    public function test_key_result_kartu_tak_bisa_ditimpa_manual(): void
    {
        $o = $this->objective();
        $kr = KeyResult::create(['objective_id' => $o->id, 'title' => 'Kolaborasi', 'source' => 'kartu', 'target' => 5, 'unit' => 'angka']);

        $this->actingAs($this->user())
            ->patch('/okr/key-results/'.$kr->id.'/actual', ['actual_manual' => 99])
            ->assertSessionHasErrors('actual_manual');
    }

    /** KR 'kartu' dibuat lewat form: metric & actual_manual dibersihkan null,
     *  satuan dipaksa 'angka' (menghitung kartu, bukan rupiah). */
    public function test_key_result_kartu_dibersihkan_saat_dibuat(): void
    {
        $o = $this->objective();

        $this->actingAs($this->user())->post('/okr/key-results', [
            'objective_id' => $o->id, 'title' => 'Langkah kolaborasi',
            'source' => 'kartu', 'target' => 5, 'unit' => 'rupiah', 'metric' => 'omset',
        ])->assertSessionHasNoErrors();

        $kr = KeyResult::first();
        $this->assertSame('kartu', $kr->source);
        $this->assertNull($kr->metric);
        $this->assertNull($kr->actual_manual);
        $this->assertSame('angka', $kr->unit);
    }

    /** Langkah dibuat DARI halaman OKR: kartu todolist baru langsung tertaut.
     *  Kanban tak lagi tahu-menahu penautan (murni delegasi). */
    public function test_langkah_baru_membuat_kartu_todolist_tertaut(): void
    {
        $o = $this->objective();
        $kr = KeyResult::create(['objective_id' => $o->id, 'title' => 'Kolaborasi', 'source' => 'kartu', 'target' => 5, 'unit' => 'angka']);

        $this->actingAs($this->user())
            ->post("/okr/key-results/{$kr->id}/kartu", ['endorse' => 'DM kreator'])
            ->assertSessionHasNoErrors();

        $kartu = Pipeline::firstWhere('endorse', 'DM kreator');
        $this->assertNotNull($kartu);
        $this->assertSame('todolist', $kartu->category);
        $this->assertSame($kr->id, $kartu->key_result_id);
    }

    /** Kartu todolist yang sudah ada bisa ditautkan lewat endpoint attach. */
    public function test_kartu_todolist_yang_ada_bisa_ditautkan(): void
    {
        $o = $this->objective();
        $kr = KeyResult::create(['objective_id' => $o->id, 'title' => 'Kolaborasi', 'source' => 'kartu', 'target' => 5, 'unit' => 'angka']);
        $kartu = $this->kartu(['category' => 'todolist', 'endorse' => 'Kartu lama']);

        $this->actingAs($this->user())
            ->post("/okr/key-results/{$kr->id}/attach", ['pipeline_id' => $kartu->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($kr->id, $kartu->fresh()->key_result_id);
    }

    /** Melepas tautan: kartu tetap hidup di papannya, hanya key_result_id null. */
    public function test_kartu_bisa_dilepas_dari_key_result(): void
    {
        $o = $this->objective();
        $kr = KeyResult::create(['objective_id' => $o->id, 'title' => 'Kolaborasi', 'source' => 'kartu', 'target' => 5, 'unit' => 'angka']);
        $kartu = $this->kartu(['category' => 'todolist', 'endorse' => 'Langkah', 'key_result_id' => $kr->id]);

        $this->actingAs($this->user())
            ->delete("/okr/key-results/{$kr->id}/kartu/{$kartu->id}")
            ->assertSessionHasNoErrors();

        $this->assertNull($kartu->fresh()->key_result_id);
    }

    /** Gerbang server: hanya kartu board todolist yang boleh ditautkan. */
    public function test_kartu_non_todolist_ditolak_saat_attach(): void
    {
        $this->board('proyek');
        $o = $this->objective();
        $kr = KeyResult::create(['objective_id' => $o->id, 'title' => 'Kolaborasi', 'source' => 'kartu', 'target' => 5, 'unit' => 'angka']);
        $kartu = $this->kartu(['category' => 'proyek', 'endorse' => 'Kartu proyek']);

        $this->actingAs($this->user())
            ->post("/okr/key-results/{$kr->id}/attach", ['pipeline_id' => $kartu->id])
            ->assertSessionHasErrors('pipeline_id');

        $this->assertNull($kartu->fresh()->key_result_id);
    }

    /** Gerbang server: KR auto/manual tak bisa menerima langkah kartu —
     *  realisasinya tak dihitung dari kartu. */
    public function test_kr_auto_tak_bisa_menerima_langkah_kartu(): void
    {
        $o = $this->objective();
        $krAuto = KeyResult::create(['objective_id' => $o->id, 'title' => 'View', 'source' => 'auto', 'metric' => 'view', 'target' => 100, 'unit' => 'angka']);

        $this->actingAs($this->user())
            ->post("/okr/key-results/{$krAuto->id}/kartu", ['endorse' => 'Salah'])
            ->assertStatus(422);

        $this->assertNull(Pipeline::firstWhere('endorse', 'Salah'));
    }

    /** Penautan = mutasi OKR. Staff view-only harus tertolak walau request
     *  langsung — gerbangnya di server (EnsureMenuAccess), bukan cuma UI. */
    public function test_staff_tak_bisa_menautkan_kartu(): void
    {
        $o = $this->objective();
        $kr = KeyResult::create(['objective_id' => $o->id, 'title' => 'Kolaborasi', 'source' => 'kartu', 'target' => 5, 'unit' => 'angka']);

        $this->actingAs($this->user('staff'))
            ->post("/okr/key-results/{$kr->id}/kartu", ['endorse' => 'X'])
            ->assertForbidden();
    }

    // ---------------------------------------------------- salin kuartal

    public function test_salin_kuartal_lalu_membawa_target_tapi_bukan_realisasi(): void
    {
        $lalu = Objective::create(['year' => 2026, 'quarter' => 2, 'title' => 'Tujuan lama']);
        KeyResult::create(['objective_id' => $lalu->id, 'title' => 'Klien baru', 'source' => 'manual', 'target' => 10, 'actual_manual' => 7, 'unit' => 'angka']);

        $this->actingAs($this->user())->post('/okr/salin', ['year' => 2026, 'quarter' => 3])
            ->assertSessionHasNoErrors();

        $baru = Objective::where('year', 2026)->where('quarter', 3)->first();
        $this->assertNotNull($baru);
        $kr = $baru->keyResults->first();
        $this->assertSame('10.00', $kr->target);        // target ikut
        $this->assertNull($kr->actual_manual);          // realisasi TIDAK ikut
    }

    /** Menyalin ke kuartal yang sudah berisi menghasilkan Objective kembar
     *  bertarget berbeda. Tombolnya disembunyikan di UI, tapi request langsung
     *  harus ikut ditolak. */
    public function test_salin_ditolak_bila_kuartal_tujuan_sudah_berisi(): void
    {
        Objective::create(['year' => 2026, 'quarter' => 2, 'title' => 'Lama']);
        Objective::create(['year' => 2026, 'quarter' => 3, 'title' => 'Sudah ada']);

        $this->actingAs($this->user())->post('/okr/salin', ['year' => 2026, 'quarter' => 3])
            ->assertSessionHasErrors('quarter');

        $this->assertSame(1, Objective::where('year', 2026)->where('quarter', 3)->count());
    }

    /** Target board = mutasi. Staff view-only harus tertolak walau request
     *  dikirim langsung, bukan cuma tombolnya disembunyikan di Vue. */
    public function test_staff_tak_bisa_menetapkan_target_board(): void
    {
        $this->board();

        $this->actingAs($this->user('staff'))->post('/kpi/targets', [
            'board_key' => 'proyek', 'year' => 2026, 'quarter' => 3, 'target_done' => 5,
        ])->assertForbidden();

        $this->assertDatabaseCount('board_quarter_targets', 0);
    }

    public function test_target_board_disimpan_dan_bisa_dikoreksi(): void
    {
        $this->board();
        $owner = $this->user();

        foreach ([5, 9] as $target) {
            $this->actingAs($owner)->post('/kpi/targets', [
                'board_key' => 'proyek', 'year' => 2026, 'quarter' => 3, 'target_done' => $target,
            ])->assertSessionHasNoErrors();
        }

        // Koreksi tak boleh melanggar unique (board, tahun, kuartal).
        $this->assertDatabaseCount('board_quarter_targets', 1);
        $this->assertSame(9, BoardQuarterTarget::first()->target_done);
    }

    /** KR otomatis tanpa metrik tak punya sumber angka & akan selamanya 0. */
    public function test_key_result_otomatis_wajib_punya_metrik(): void
    {
        $o = $this->objective();

        $this->actingAs($this->user())->post('/okr/key-results', [
            'objective_id' => $o->id, 'title' => 'Tanpa metrik',
            'source' => 'auto', 'target' => 100, 'unit' => 'angka',
        ])->assertSessionHasErrors('metric');
    }

    public function test_metrik_tak_dikenal_ditolak(): void
    {
        $o = $this->objective();

        $this->actingAs($this->user())->post('/okr/key-results', [
            'objective_id' => $o->id, 'title' => 'X',
            'source' => 'auto', 'metric' => 'ngawur', 'target' => 10, 'unit' => 'angka',
        ])->assertSessionHasErrors('metric');
    }

    // --------------------------------------------------------- realisasi

    /** Realisasi dihitung dari modul sumber, bukan diketik. Ketiganya diuji
     *  sekaligus supaya batas kuartalnya ikut terverifikasi. */
    public function test_realisasi_dihitung_dari_insight_dan_pembukuan(): void
    {
        // Q3 2026 = 1 Jul s/d 30 Sep.
        InsightContent::create(['platform' => 'youtube', 'content_id' => 'a', 'judul' => 'A', 'published_at' => '2026-07-10', 'views' => 1000]);
        InsightContent::create(['platform' => 'youtube', 'content_id' => 'b', 'judul' => 'B', 'published_at' => '2026-09-30', 'views' => 500]);
        InsightContent::create(['platform' => 'youtube', 'content_id' => 'c', 'judul' => 'Luar', 'published_at' => '2026-10-01', 'views' => 9999]);

        Transaction::create(['type' => 'pemasukan', 'category' => 'jasa', 'amount_idr' => 300, 'date' => '2026-08-01']);
        Transaction::create(['type' => 'pengeluaran', 'category' => 'jasa', 'amount_idr' => 100, 'date' => '2026-08-02']);
        Transaction::create(['type' => 'pemasukan', 'category' => 'jasa', 'amount_idr' => 777, 'date' => '2026-11-01']);

        $hasil = OkrMetrics::realisasi(2026, 3);

        $this->assertSame(1500.0, $hasil['view']);      // konten Oktober tak ikut
        $this->assertSame(300.0, $hasil['omset']);      // pengeluaran & luar kuartal tak ikut
    }

    /** Subscriber = POSISI pada snapshot terakhir per akun, bukan penjumlahan
     *  seluruh baris harian — kalau dijumlah, satu orang terhitung berkali-kali. */
    public function test_subscriber_memakai_snapshot_terakhir_per_akun(): void
    {
        InsightAccount::create(['platform' => 'instagram', 'akun' => 'a', 'tanggal' => '2026-07-01', 'followers' => 100]);
        InsightAccount::create(['platform' => 'instagram', 'akun' => 'a', 'tanggal' => '2026-08-01', 'followers' => 150]);
        InsightAccount::create(['platform' => 'youtube', 'akun' => 'b', 'tanggal' => '2026-07-15', 'followers' => 40]);

        // 150 (snapshot terakhir akun a) + 40 (akun b) = 190, bukan 290.
        $this->assertSame(190.0, OkrMetrics::realisasi(2026, 3)['subscriber']);
    }

    // --------------------------------------------------- created_by kartu

    public function test_pembuat_kartu_dicatat_dari_sesi(): void
    {
        $this->board();
        $owner = $this->user();

        $this->actingAs($owner)->post('/pipelines', [
            'category' => 'proyek', 'endorse' => 'Task baru', 'account' => 'fk',
            'progress' => 'todo', 'payment_status' => 'belum',
        ])->assertSessionHasNoErrors();

        $this->assertSame($owner->id, Pipeline::first()->created_by);
    }

    public function test_pembuat_board_dicatat(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->post('/boards', ['name' => 'Board Baru'])->assertSessionHasNoErrors();

        $this->assertSame($owner->id, Category::where('key', 'board_baru')->value('created_by'));
    }

    // -------------------------------------------------- completed_at

    public function test_tandai_selesai_menstempel_waktu_dan_membatalkannya_menghapus(): void
    {
        $this->board();
        $kartu = $this->kartu();
        $owner = $this->user();

        $this->actingAs($owner)->patch("/pipelines/{$kartu->id}/done", ['done' => true])->assertOk();
        $this->assertNotNull($kartu->fresh()->completed_at);

        $this->actingAs($owner)->patch("/pipelines/{$kartu->id}/done", ['done' => false])->assertOk();
        $this->assertNull($kartu->fresh()->completed_at);
    }

    /** Drag ke kolom TERAKHIR = selesai. Ini cara paling lazim menyelesaikan
     *  kartu; tanpa stempel di sini, analitik ketepatan hampir selalu kosong. */
    public function test_drag_ke_kolom_terakhir_menstempel_selesai(): void
    {
        $this->board();
        $kartu = $this->kartu();

        $this->actingAs($this->user())
            ->patch('/pipelines/reorder', ['progress' => 'done', 'ids' => [$kartu->id]])
            ->assertOk();

        $this->assertNotNull($kartu->fresh()->completed_at);

        // Ditarik keluar lagi → stempel dicabut, kartu kembali "berjalan".
        $this->actingAs($this->user())
            ->patch('/pipelines/reorder', ['progress' => 'progress', 'ids' => [$kartu->id]])
            ->assertOk();

        $this->assertNull($kartu->fresh()->completed_at);
    }

    /**
     * Kartu yang cuma IKUT dalam kiriman drag tak boleh ikut distempel.
     *
     *  Regresi nyata: kiriman drag berisi seluruh isi kolom tujuan (lihat
     *  Kanban.vue), jadi satu kartu yang masuk membawa serta semua kartu lama
     *  yang sudah duduk di situ. Dulu semuanya ikut distempel "hari ini" —
     *  deadline mereka sudah lewat berbulan-bulan, jadi satu drag membuat
     *  seluruh papan terbaca terlambat. Tes lama tak menangkapnya karena
     *  selalu mengirim ids berisi kartu yang memang sedang dipindahkan.
     */
    public function test_kartu_yang_hanya_ikut_dalam_kiriman_drag_tak_distempel(): void
    {
        $this->board();

        // Sudah lama duduk di kolom terakhir, belum pernah punya stempel —
        // persis keadaan data lama sesudah kolom completed_at ditambahkan.
        $lama = $this->kartu(['progress' => 'done', 'deadline' => '2026-01-10']);
        $baru = $this->kartu(['progress' => 'todo', 'deadline' => '2026-12-31']);

        // Bentuk kiriman asli vuedraggable: SELURUH isi kolom tujuan.
        $this->actingAs($this->user())
            ->patch('/pipelines/reorder', ['progress' => 'done', 'ids' => [$lama->id, $baru->id]])
            ->assertOk();

        $this->assertNull($lama->fresh()->completed_at, 'Kartu yang tak berpindah kolom ikut distempel.');
        // 'lewat' (belum dinilai selesai), BUKAN 'terlambat'. Bedanya penting:
        // 'lewat' tak masuk hitungan rasio ketepatan, 'terlambat' masuk — dan
        // itulah yang dulu membuat rasio anjlok sesudah satu drag.
        $this->assertSame('lewat', $lama->fresh()->ketepatan());
        $this->assertNotNull($baru->fresh()->completed_at, 'Kartu yang benar-benar masuk tak distempel.');
    }

    /** Kartu yang cuma ikut geser di kolom terakhir tak boleh kehilangan
     *  stempelnya — urutan berubah, status tidak. */
    public function test_geser_urutan_di_kolom_terakhir_tak_mencabut_stempel(): void
    {
        $this->board();
        $a = $this->kartu(['progress' => 'done', 'completed_at' => '2026-03-01 09:00:00']);
        $b = $this->kartu(['progress' => 'done', 'completed_at' => '2026-03-02 09:00:00']);

        $this->actingAs($this->user())
            ->patch('/pipelines/reorder', ['progress' => 'done', 'ids' => [$b->id, $a->id]])
            ->assertOk();

        $this->assertSame('2026-03-01 09:00:00', $a->fresh()->completed_at->toDateTimeString());
        $this->assertSame('2026-03-02 09:00:00', $b->fresh()->completed_at->toDateTimeString());
    }

    /** Stempel pertama dipertahankan: kalau ditimpa, kartu terlambat bisa
     *  "dirapikan" jadi tepat waktu hanya dgn menyentuhnya ulang. */
    public function test_stempel_selesai_tidak_ditimpa_saat_diselesaikan_ulang(): void
    {
        $this->board();
        $kartu = $this->kartu(['completed_at' => '2026-01-05 08:00:00']);

        $this->actingAs($this->user())
            ->patch('/pipelines/reorder', ['progress' => 'done', 'ids' => [$kartu->id]])
            ->assertOk();

        $this->assertSame('2026-01-05 08:00:00', $kartu->fresh()->completed_at->toDateTimeString());
    }

    // ----------------------------------------------------- ketepatan

    public function test_klasifikasi_ketepatan_kartu(): void
    {
        $this->board();

        $tepat = $this->kartu(['deadline' => '2026-07-10', 'completed_at' => '2026-07-10 23:00:00']);
        $telat = $this->kartu(['deadline' => '2026-07-10', 'completed_at' => '2026-07-11 01:00:00']);
        $lewat = $this->kartu(['deadline' => '2020-01-01']);
        $tanpa = $this->kartu(['deadline' => null, 'completed_at' => now()]);

        // Selesai di HARI deadline = tepat: deadline disimpan sbg tanggal, jadi
        // perbandingannya per tanggal, bukan per detik.
        $this->assertSame('tepat', $tepat->ketepatan());
        $this->assertSame('terlambat', $telat->ketepatan());
        $this->assertSame('lewat', $lewat->ketepatan());
        // Tanpa deadline TIDAK dihitung tepat — kalau dihitung, rasio ketepatan
        // menggelembung oleh kartu yang tak pernah punya janji waktu.
        $this->assertNull($tanpa->ketepatan());
    }

    // ------------------------------------------------- filter kuartal

    /** Filter kuartal menyaring lewat DEADLINE, dan kartu tanpa deadline
     *  memang tak muncul saat filter aktif. */
    public function test_filter_kuartal_menyaring_kartu_berdasarkan_deadline(): void
    {
        $this->board();
        $this->kartu(['endorse' => 'Q3', 'deadline' => '2026-08-15']);
        $this->kartu(['endorse' => 'Q4', 'deadline' => '2026-11-15']);
        $this->kartu(['endorse' => 'Tanpa deadline', 'deadline' => null]);

        $this->actingAs($this->user())
            ->get('/pipelines/kanban?category=proyek&q=2026-Q3')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('quarter.filtering', true)
                ->where('quarter.key', '2026-Q3')
                ->where('quarterStats.no_deadline', 1)
                ->has('board.todo', 1)                       // hanya kartu Q3
            );
    }

    /** Tanpa ?q kartu tidak disaring, tapi panel tetap punya kuartal acuan. */
    public function test_tanpa_parameter_kuartal_kartu_tidak_disaring(): void
    {
        $this->board();
        $this->kartu(['deadline' => '2026-08-15']);
        $this->kartu(['deadline' => '2026-11-15']);

        $this->actingAs($this->user())
            ->get('/pipelines/kanban?category=proyek')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('quarter.filtering', false)
                ->has('board.todo', 2)
            );
    }

    public function test_parameter_kuartal_ngawur_diabaikan_bukan_error(): void
    {
        $this->board();

        $this->actingAs($this->user())
            ->get('/pipelines/kanban?category=proyek&q=bukan-kuartal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('quarter.filtering', false));
    }

    // --------------------------------------------------- target board

    /** Angka board di halaman Kanban & halaman OKR wajib sama untuk kuartal
     *  yang sama — keduanya memanggil KpiController::statistik(), bukan
     *  menyalin rumus. */
    public function test_capaian_target_board_konsisten_di_kanban_dan_kpi(): void
    {
        $this->board();
        $q = Quarter::current();
        [$start] = Quarter::range($q['year'], $q['quarter']);
        $tgl = $start->addDays(3)->toDateString();

        $this->kartu(['deadline' => $tgl, 'completed_at' => $start->addDays(2)]);
        $this->kartu(['deadline' => $tgl, 'completed_at' => $start->addDays(9)]);   // sesudah deadline
        $this->kartu(['deadline' => $tgl]);                                          // belum selesai

        BoardQuarterTarget::create([
            'board_key' => 'proyek', 'year' => $q['year'], 'quarter' => $q['quarter'], 'target_done' => 4,
        ]);

        $owner = $this->user();

        $this->actingAs($owner)->get('/pipelines/kanban?category=proyek')
            ->assertInertia(fn ($page) => $page
                ->where('quarterStats.target', 4)
                ->where('quarterStats.done', 2)
                ->where('quarterStats.percent', 50)
                ->where('quarterStats.ketepatan.tepat', 1)
                ->where('quarterStats.ketepatan.terlambat', 1)
            );

        $this->actingAs($owner)->get('/kpi')
            ->assertInertia(fn ($page) => $page
                ->where('board.0.target', 4)
                ->where('board.0.done', 2)
                ->where('board.0.percent', 50)
                // Rekap lintas board dihitung server, bukan di Vue.
                ->where('total.tepat', 1)
                ->where('total.terlambat', 1)
                ->where('total.persen_tepat', 50)
            );
    }

    /** Target belum ditetapkan → persen null, BUKAN 0. Dua keadaan itu beda
     *  arti dan UI menampilkannya berbeda. */
    public function test_tanpa_target_persen_bernilai_null(): void
    {
        $this->board();

        $this->actingAs($this->user())->get('/pipelines/kanban?category=proyek')
            ->assertInertia(fn ($page) => $page->where('quarterStats.percent', null));
    }
}
