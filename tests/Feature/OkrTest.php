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

    /** Peran IT adalah super admin dan dapat membuka seluruh menu. */
    public function test_it_bisa_membuka_okr(): void
    {
        $this->actingAs($this->user('it'))->get('/okr')->assertOk();
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
    public function test_it_bisa_mengelola_okr_sebagai_super_admin(): void
    {
        $it = $this->user('it');

        $this->actingAs($it)->post('/okr/objectives', [
            'year' => 2026, 'quarter' => 3, 'title' => 'X',
        ])->assertSessionHasNoErrors();
        $this->actingAs($it)->post('/okr/key-results', [
            'objective_id' => Objective::first()->id,
            'title' => 'X',
            'source' => 'manual',
            'target' => 1,
            'unit' => 'angka',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('objectives', 1);
        $this->assertDatabaseCount('key_results', 1);
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

    /** Target omzet menjadi bagian Objective, bukan KR tambahan. */
    public function test_buat_objective_menyimpan_target_omzet_pada_objective(): void
    {
        $owner = $this->user();

        $this->actingAs($owner)->post('/okr/objectives', [
            'year' => 2026,
            'quarter' => 3,
            'title' => 'Bisnis tumbuh sehat',
            'priority_name' => 'Urgent',
            'omset_target' => 250_000_000,
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Objective dan target omzet ditambahkan.');

        $objective = Objective::firstOrFail();

        $this->assertSame('250000000.00', $objective->omset_target);
        $this->assertSame($owner->id, $objective->omset_owner_id);
        $this->assertSame('Urgent', $objective->priority['name']);
        $this->assertDatabaseCount('key_results', 0);
    }

    /** PIC omzet boleh berupa staff. Penugasannya harus tersimpan pada Objective dan
     *  notifikasi database harus terlihat dari halaman yang memang boleh dibuka
     *  staff—bukan mengarahkan staff ke /okr yang akan 403. */
    public function test_pic_staff_menerima_notifikasi_target_omzet_dari_server(): void
    {
        $owner = $this->user();
        $staff = $this->user('staff');

        $this->actingAs($owner)->post('/okr/objectives', [
            'year' => 2026,
            'quarter' => 3,
            'title' => 'Omzet bertumbuh',
            'priority_name' => 'Urgent',
            'omset_target' => 125_000_000,
            'omset_owner_id' => $staff->id,
        ])->assertSessionHasNoErrors();

        $objective = Objective::firstOrFail();
        $this->assertSame($staff->id, $objective->omset_owner_id);
        $this->assertDatabaseCount('key_results', 0);

        $notification = $staff->notifications()->firstOrFail();
        $this->assertSame('Target omzet baru', $notification->data['title']);
        $this->assertStringContainsString('Rp 125.000.000', $notification->data['message']);
        $this->assertNull($notification->data['url']);
        $this->assertSame($objective->id, $notification->data['objective_id']);
        $this->assertNull($notification->data['key_result_id']);
        $this->assertSame('Urgent', $notification->data['priority']['name']);

        // Shared prop berasal dari database server dan tersedia di Layout global.
        $this->actingAs($staff)->get('/kpi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('unreadNotificationsCount', 1)
                ->where('serverNotifications.0.id', $notification->id)
                ->where('serverNotifications.0.title', 'Target omzet baru')
                ->where('serverNotifications.0.read_at', null)
            );

        // User lain tidak boleh menandai notifikasi staff sebagai sudah dibaca.
        $this->actingAs($owner)
            ->patch("/notifications/{$notification->id}/read")
            ->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);

        $this->actingAs($staff)
            ->patch("/notifications/{$notification->id}/read")
            ->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_staff_dapat_menandai_semua_notifikasinya_sudah_dibaca(): void
    {
        $owner = $this->user();
        $staff = $this->user('staff');

        foreach (['Target A', 'Target B'] as $title) {
            $this->actingAs($owner)->post('/okr/objectives', [
                'year' => 2026,
                'quarter' => 3,
                'title' => $title,
                'omset_target' => 10_000_000,
                'omset_owner_id' => $staff->id,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(2, $staff->unreadNotifications()->count());

        $this->actingAs($staff)
            ->patch('/notifications/read-all')
            ->assertSessionHas('status', 'Semua notifikasi sudah dibaca.');

        $this->assertSame(0, $staff->unreadNotifications()->count());
    }

    /** Nilai omzet tidak sah harus menolak seluruh request sebelum Objective tersimpan. */
    public function test_target_omzet_objective_tidak_boleh_negatif(): void
    {
        $this->actingAs($this->user())->post('/okr/objectives', [
            'year' => 2026,
            'quarter' => 3,
            'title' => 'Bisnis tumbuh sehat',
            'omset_target' => -1,
        ])->assertSessionHasErrors('omset_target');

        $this->assertDatabaseCount('objectives', 0);
        $this->assertDatabaseCount('key_results', 0);
    }

    /** Omzet bukan jenis Key Result lagi; targetnya hanya hidup di Objective. */
    public function test_key_result_omzet_baru_ditolak_karena_milik_objective(): void
    {
        $objective = $this->objective();

        $this->actingAs($this->user())->post('/okr/key-results', [
            'objective_id' => $objective->id,
            'title' => 'Omzet lain',
            'source' => 'auto',
            'metric' => 'omset',
            'target' => 100_000_000,
            'unit' => 'rupiah',
        ])->assertSessionHasErrors('metric');

        $this->assertDatabaseCount('key_results', 0);
    }

    /** Urgent/Penting memakai preset label Kanban yang sama, tetapi snapshot-nya
     *  disimpan pada item OKR supaya tetap tampil bila preset kelak berubah. */
    public function test_status_urgent_dan_penting_tersimpan_dan_tampil_di_okr(): void
    {
        $owner = $this->user();

        $this->actingAs($owner)->post('/okr/objectives', [
            'year' => 2026,
            'quarter' => 3,
            'title' => 'Pertumbuhan prioritas',
            'priority_name' => 'Urgent',
        ])->assertSessionHasNoErrors();

        $objective = Objective::firstOrFail();
        $this->assertSame(
            ['name' => 'Urgent', 'color' => 'bg-red-500'],
            $objective->priority
        );

        $this->actingAs($owner)->post('/okr/key-results', [
            'objective_id' => $objective->id,
            'title' => 'Capai target utama',
            'source' => 'manual',
            'target' => 10,
            'unit' => 'angka',
            'priority_name' => 'Penting',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            ['name' => 'Penting', 'color' => 'bg-amber-500'],
            KeyResult::firstOrFail()->priority
        );

        $this->actingAs($owner)->get('/okr?q=2026-Q3')
            ->assertInertia(fn ($page) => $page
                ->where('priorities.0.name', 'Urgent')
                ->where('priorities.1.name', 'Penting')
                ->where('objectives.0.priority.name', 'Urgent')
                ->where('objectives.0.key_results.0.priority.name', 'Penting')
            );
    }

    /** Browser tidak boleh menyisipkan nama/warna status sendiri. */
    public function test_status_okr_di_luar_urgent_dan_penting_ditolak(): void
    {
        $this->actingAs($this->user())->post('/okr/objectives', [
            'year' => 2026,
            'quarter' => 3,
            'title' => 'Status tidak sah',
            'priority_name' => 'Review',
        ])->assertSessionHasErrors('priority_name');

        $this->assertDatabaseCount('objectives', 0);
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

    public function test_target_omzet_menjadi_progress_objective_tanpa_key_result(): void
    {
        $o = Objective::create([
            'year' => 2026,
            'quarter' => 3,
            'title' => 'Bisnis sehat',
            'omset_target' => 200_000_000,
        ]);

        $this->assertSame(50.0, $o->progress(['omset' => 100_000_000]));
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
            'source' => 'kartu', 'board_key' => 'todolist', 'target' => 5, 'unit' => 'rupiah', 'metric' => 'omset',
        ])->assertSessionHasNoErrors();

        $kr = KeyResult::first();
        $this->assertSame('kartu', $kr->source);
        $this->assertNull($kr->metric);
        $this->assertNull($kr->actual_manual);
        $this->assertSame('angka', $kr->unit);
    }

    public function test_card_biasa_tidak_bisa_menautkan_diri_ke_key_result(): void
    {
        $owner = $this->user();
        $objective = $this->objective(2026, 3);
        $kr = KeyResult::create([
            'objective_id' => $objective->id,
            'title' => 'Naikkan konversi',
            'source' => 'manual',
            'target' => 20,
            'unit' => 'persen',
        ]);

        $this->actingAs($owner)->post('/pipelines', [
            'category' => 'todolist',
            'endorse' => 'Follow up prospek',
            'progress' => 'todo',
            'account' => 'fk',
            'payment_status' => 'belum',
            'key_result_id' => $kr->id,
        ])->assertSessionHasNoErrors();

        $card = Pipeline::firstWhere('endorse', 'Follow up prospek');
        $this->assertNull($card->key_result_id);
    }

    public function test_membuat_kr_membuat_satu_card_utama_dan_tugas_bisa_didelegasikan(): void
    {
        $owner = $this->user();
        $staff = $this->user('staff');
        $objective = $this->objective();
        $initialColumn = BoardColumn::where('board_key', 'todolist')->orderBy('position')->value('key');

        $this->actingAs($owner)->post('/okr/key-results', [
            'objective_id' => $objective->id,
            'title' => 'Dapatkan 20 klien',
            'source' => 'manual',
            'target' => 20,
            'unit' => 'angka',
            'kanban_board_key' => 'todolist',
            'kanban_column_key' => $initialColumn,
            'card_category' => 'Penting',
            'card_description' => 'Konversi prospek sampai menjadi klien aktif.',
            'assigned_to' => $staff->id,
            'deadline' => '2026-09-30',
        ])->assertSessionHasNoErrors();

        $card = Pipeline::firstWhere('is_kr_master', true);
        $this->assertNotNull($card);
        $this->assertSame(KeyResult::first()->id, $card->key_result_id);
        $this->assertSame($staff->id, $card->assigned_to);
        $this->assertSame($staff->id, KeyResult::first()->owner_id);
        $this->assertSame($initialColumn, $card->progress);
        $this->assertSame('Konversi prospek sampai menjadi klien aktif.', $card->description);
        $this->assertSame('Penting', $card->labels[0]['name']);

        // Penugasan bukan hanya reminder deadline: staff langsung mendapat
        // notifikasi persisten dengan tautan ke card Kanban yang dapat ia buka.
        $notification = $staff->notifications()->firstOrFail();
        $this->assertSame('Pekerjaan OKR baru', $notification->data['title']);
        $this->assertSame(route('pipelines.kanban', [
            'category' => $card->category,
            'card' => $card->id,
        ]), $notification->data['url']);

        $this->actingAs($owner)->post("/pipelines/{$card->id}/tasks", [
            'title' => 'Hubungi prospek',
            'assigned_to' => $staff->id,
            'deadline' => '2026-08-10',
        ])->assertSessionHasNoErrors();

        $task = $card->tasks()->first();
        $this->actingAs($staff)->patch("/pipeline-tasks/{$task->id}", ['done' => true])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertTrue((bool) $card->fresh()->done);
        $this->assertNotNull($card->fresh()->completed_at);

        // Penyelesaian lewat sync tugas ikut melaporkan ke pemilik OKR;
        // pelaku (staff) tak menerima laporan atas pekerjaannya sendiri.
        $laporan = $owner->notifications()->firstOrFail();
        $this->assertSame('okr_selesai', $laporan->data['kind']);
        $this->assertSame('Pekerjaan OKR selesai', $laporan->data['title']);
        $this->assertSame(1, $staff->notifications()->count());
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
        $owner = $this->user();
        $lalu = Objective::create([
            'year' => 2026,
            'quarter' => 2,
            'title' => 'Tujuan lama',
            'omset_target' => 50_000_000,
            'omset_owner_id' => $owner->id,
        ]);
        KeyResult::create(['objective_id' => $lalu->id, 'title' => 'Klien baru', 'source' => 'manual', 'target' => 10, 'actual_manual' => 7, 'unit' => 'angka']);

        $this->actingAs($owner)->post('/okr/salin', ['year' => 2026, 'quarter' => 3])
            ->assertSessionHasNoErrors();

        $baru = Objective::where('year', 2026)->where('quarter', 3)->first();
        $this->assertNotNull($baru);
        $kr = $baru->keyResults->first();
        $this->assertSame('50000000.00', $baru->omset_target);
        $this->assertSame($owner->id, $baru->omset_owner_id);
        $this->assertSame('10.00', $kr->target);        // target ikut
        $this->assertNull($kr->actual_manual);          // realisasi TIDAK ikut
    }

    public function test_halaman_dan_tren_membaca_target_omzet_dari_objective(): void
    {
        $owner = $this->user();
        Objective::create([
            'year' => 2026,
            'quarter' => 3,
            'title' => 'Pertumbuhan omzet',
            'omset_target' => 200_000_000,
            'omset_owner_id' => $owner->id,
        ]);
        Transaction::create([
            'type' => 'pemasukan',
            'category' => 'jasa',
            'amount_idr' => 100_000_000,
            'date' => '2026-08-01',
        ]);

        $this->actingAs($owner)->get('/okr?q=2026-Q3')
            ->assertInertia(fn ($page) => $page
                ->where('objectives.0.omset_target', 200_000_000)
                ->where('objectives.0.omset_actual', 100_000_000)
                ->where('objectives.0.omset_percent', 50)
                ->where('objectives.0.omset_owner_name', $owner->name)
                ->where('objectives.0.progress', 50)
                ->where('objectives.0.key_results', [])
                ->where('tren.2.metric', 'omset')
                ->where('tren.2.points.5.target', 200_000_000)
                ->where('tren.2.points.5.actual', 100_000_000)
                ->where('tren.2.points.5.percent', 50)
            );
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

    // ----------------------------------------------------- ketepatan

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

    /** Target belum ditetapkan → persen null, BUKAN 0. Dua keadaan itu beda
     *  arti dan UI menampilkannya berbeda. */
    public function test_tanpa_target_persen_bernilai_null(): void
    {
        $this->board();

        $this->actingAs($this->user())->get('/pipelines/kanban?category=proyek')
            ->assertInertia(fn ($page) => $page->where('quarterStats.percent', null));
    }

    // ------------------------------------------------- notifikasi lanjutan

    /** Helper: KR + card utama di todolist yang ditugaskan ke $pic. */
    private function krDenganCard(User $pic, array $override = []): array
    {
        $objective = $this->objective();
        $initialColumn = BoardColumn::where('board_key', 'todolist')->orderBy('position')->value('key');

        $this->post('/okr/key-results', array_merge([
            'objective_id' => $objective->id,
            'title' => 'Dapatkan 20 klien',
            'source' => 'manual',
            'target' => 20,
            'unit' => 'angka',
            'kanban_board_key' => 'todolist',
            'kanban_column_key' => $initialColumn,
            'assigned_to' => $pic->id,
        ], $override))->assertSessionHasNoErrors();

        return [KeyResult::firstOrFail(), Pipeline::firstWhere('is_kr_master', true)];
    }

    /** KR tanpa card eksekusi: penanggung jawab tetap diberi tahu, tanpa
     *  tautan Kanban — pekerjaannya memang tak punya card. */
    public function test_kr_tanpa_card_tetap_memberi_tahu_penanggung_jawab(): void
    {
        $owner = $this->user();
        $staff = $this->user('staff');
        $objective = $this->objective();

        $this->actingAs($owner)->post('/okr/key-results', [
            'objective_id' => $objective->id,
            'title' => 'Susun playbook penjualan',
            'source' => 'manual',
            'target' => 1,
            'unit' => 'angka',
            'assigned_to' => $staff->id,
        ])->assertSessionHasNoErrors();

        $kr = KeyResult::firstOrFail();
        $this->assertSame($staff->id, $kr->owner_id);
        $this->assertDatabaseCount('pipelines', 0);   // tak ada card eksekusi

        $notification = $staff->notifications()->firstOrFail();
        $this->assertSame('Penanggung jawab KR baru', $notification->data['title']);
        $this->assertNull($notification->data['url']);
        $this->assertSame($kr->id, $notification->data['key_result_id']);
    }

    /** Menugaskan diri sendiri = tak ada notifikasi. Kabar untuk keputusan
     *  yang baru saja diambil sendiri hanyalah derau di lonceng. */
    public function test_kr_tanpa_pic_tak_mengirim_notifikasi_ke_pembuat(): void
    {
        $owner = $this->user();
        $objective = $this->objective();

        $this->actingAs($owner)->post('/okr/key-results', [
            'objective_id' => $objective->id,
            'title' => 'Riset pasar',
            'source' => 'manual',
            'target' => 1,
            'unit' => 'angka',
        ])->assertSessionHasNoErrors();

        $this->assertSame($owner->id, KeyResult::firstOrFail()->owner_id);
        $this->assertDatabaseCount('notifications', 0);
    }

    /** Pemilik OKR menerima laporan saat PIC menandai card selesai — dan tak
     *  ada laporan kedua saat tombol yang sama ditekan ulang. */
    public function test_pemilik_okr_menerima_laporan_saat_card_tertaut_selesai(): void
    {
        $owner = $this->user();
        $staff = $this->user('staff');
        $this->actingAs($owner);
        [, $card] = $this->krDenganCard($staff);

        $this->actingAs($staff)->patch("/pipelines/{$card->id}/done", ['done' => true])->assertOk();

        $laporan = $owner->notifications()->firstOrFail();
        $this->assertSame('okr_selesai', $laporan->data['kind']);
        $this->assertSame('Pekerjaan OKR selesai', $laporan->data['title']);
        $this->assertStringContainsString($staff->name, $laporan->data['message']);
        // Owner boleh membuka /okr → tautan disertakan.
        $this->assertSame(route('okr.index'), $laporan->data['url']);

        // Staff hanya punya notifikasi penugasan; tak ada laporan untuk diri sendiri.
        $this->assertSame(1, $staff->notifications()->count());

        // Menekan ulang tombol selesai bukan transisi baru → tak ada laporan kedua.
        $this->actingAs($staff)->patch("/pipelines/{$card->id}/done", ['done' => true])->assertOk();
        $this->assertSame(1, $owner->notifications()->count());
    }

    /** Koreksi PIC card utama dari edit KR: PIC lama diberi tahu dialihkan,
     *  PIC baru menerima penugasan, dan owner KR mengikuti PIC baru. */
    public function test_mengubah_pic_card_utama_memberi_tahu_pic_lama_dan_baru(): void
    {
        $owner = $this->user();
        $staffLama = $this->user('staff');
        $staffBaru = $this->user('staff');
        $this->actingAs($owner);
        [$kr, $card] = $this->krDenganCard($staffLama);

        $this->put("/okr/key-results/{$kr->id}", [
            'title' => 'Dapatkan 20 klien',
            'source' => 'manual',
            'target' => 20,
            'unit' => 'angka',
            'assigned_to' => $staffBaru->id,
            'deadline' => '2026-09-30',
        ])->assertSessionHasNoErrors();

        $this->assertSame($staffBaru->id, $card->fresh()->assigned_to);
        $this->assertSame($staffBaru->id, $kr->fresh()->owner_id);

        // Notifikasi dicari per judul, bukan latest('id'): primary key tabel
        // notifications adalah UUID, jadi urutan id bukan urutan waktu.
        $alih = $staffLama->notifications()->get()
            ->first(fn ($n) => ($n->data['title'] ?? null) === 'Penugasan OKR dialihkan');
        $this->assertNotNull($alih);
        $this->assertSame('okr_perubahan', $alih->data['kind']);
        $this->assertNull($alih->data['url']);   // bukan lagi miliknya

        $baru = $staffBaru->notifications()->firstOrFail();
        $this->assertSame('Pekerjaan OKR baru', $baru->data['title']);
        $this->assertSame(route('pipelines.kanban', [
            'category' => $card->category,
            'card' => $card->id,
        ]), $baru->data['url']);
    }

    /** PIC tetap tetapi deadline diganti → PIC diberi tahu tanggal barunya. */
    public function test_mengubah_deadline_card_utama_memberi_tahu_pic(): void
    {
        $owner = $this->user();
        $staff = $this->user('staff');
        $this->actingAs($owner);
        [$kr, $card] = $this->krDenganCard($staff, ['deadline' => '2026-09-30']);

        $this->put("/okr/key-results/{$kr->id}", [
            'title' => 'Dapatkan 20 klien',
            'source' => 'manual',
            'target' => 20,
            'unit' => 'angka',
            'assigned_to' => $staff->id,
            'deadline' => '2026-09-15',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-09-15', $card->fresh()->deadline->toDateString());
        $notif = $staff->notifications()->get()
            ->first(fn ($n) => ($n->data['title'] ?? null) === 'Deadline OKR berubah');
        $this->assertNotNull($notif);
        $this->assertStringContainsString('2026-09-15', $notif->data['message']);
    }

    /** Koreksi target omzet lewat edit Objective: PIC tetap → kabar angka
     *  baru; PIC diganti → lama dialihkan, baru menerima penugasan. */
    public function test_mengubah_target_dan_pic_omzet_memberi_tahu_pic_terkait(): void
    {
        $owner = $this->user();
        $staff = $this->user('staff');
        $staff2 = $this->user('staff');

        $this->actingAs($owner)->post('/okr/objectives', [
            'year' => 2026, 'quarter' => 3, 'title' => 'Omzet bertumbuh',
            'omset_target' => 125_000_000, 'omset_owner_id' => $staff->id,
        ])->assertSessionHasNoErrors();
        $objective = Objective::firstOrFail();
        $this->assertSame(1, $staff->notifications()->count());   // penugasan awal

        // Target berubah, PIC tetap.
        $this->put("/okr/objectives/{$objective->id}", [
            'year' => 2026, 'quarter' => 3, 'title' => 'Omzet bertumbuh',
            'omset_target' => 150_000_000, 'omset_owner_id' => $staff->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame('150000000.00', $objective->fresh()->omset_target);
        $notif = $staff->notifications()->get()
            ->first(fn ($n) => ($n->data['title'] ?? null) === 'Target omzet berubah');
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Rp 150.000.000', $notif->data['message']);

        // PIC diganti.
        $this->put("/okr/objectives/{$objective->id}", [
            'year' => 2026, 'quarter' => 3, 'title' => 'Omzet bertumbuh',
            'omset_target' => 150_000_000, 'omset_owner_id' => $staff2->id,
        ])->assertSessionHasNoErrors();
        $alih = $staff->notifications()->get()
            ->first(fn ($n) => ($n->data['title'] ?? null) === 'Penugasan omzet berubah');
        $this->assertNotNull($alih);
        $this->assertStringContainsString($staff2->name, $alih->data['message']);
        $this->assertSame('Target omzet baru', $staff2->notifications()->firstOrFail()->data['title']);
    }

    /** Klien lama yang belum mengirim field omzet tak boleh menghapus target
     *  yang sudah ada hanya karena mengedit judul Objective. */
    public function test_edit_objective_tanpa_field_omzet_tak_menghapus_target(): void
    {
        $owner = $this->user();
        $staff = $this->user('staff');

        $this->actingAs($owner)->post('/okr/objectives', [
            'year' => 2026, 'quarter' => 3, 'title' => 'Omzet bertumbuh',
            'omset_target' => 125_000_000, 'omset_owner_id' => $staff->id,
        ])->assertSessionHasNoErrors();
        $objective = Objective::firstOrFail();

        $this->put("/okr/objectives/{$objective->id}", [
            'year' => 2026, 'quarter' => 3, 'title' => 'Judul dikoreksi',
        ])->assertSessionHasNoErrors();

        $this->assertSame('125000000.00', $objective->fresh()->omset_target);
        $this->assertSame($staff->id, $objective->fresh()->omset_owner_id);
        $this->assertSame(1, $staff->notifications()->count());   // tak ada kabar baru
    }

    /** Pengingat deadline kartu OKR dibuat saat PIC membuka halaman — maksimal
     *  satu per kartu per hari, jadi kunjungan ulang tak menumpuknya. */
    public function test_reminder_deadline_okr_dibuat_sekali_sehari(): void
    {
        $owner = $this->user();
        $staff = $this->user('staff');
        $this->actingAs($owner);
        [, $card] = $this->krDenganCard($staff, ['deadline' => now()->addDays(2)->toDateString()]);

        $this->actingAs($staff)->get('/kpi')->assertOk();

        $reminders = fn () => $staff->notifications()->get()
            ->filter(fn ($n) => ($n->data['kind'] ?? null) === 'okr_deadline');

        $this->assertCount(1, $reminders());
        $first = $reminders()->first();
        $this->assertSame('Pengingat deadline OKR', $first->data['title']);
        $this->assertStringContainsString('tinggal 2 hari', $first->data['message']);
        $this->assertSame($card->id, $first->data['pipeline_id']);
        $this->assertSame(route('pipelines.kanban', [
            'category' => $card->category,
            'card' => $card->id,
        ]), $first->data['url']);

        // Kunjungan ulang di hari yang sama tak menambah pengingat.
        $this->actingAs($staff)->get('/kpi')->assertOk();
        $this->assertCount(1, $reminders());
    }

    /** Kartu yang bukan milik OKR tak menghasilkan pengingat deadline OKR —
     *  reminder-nya sudah ditangani workReminders yang dihitung langsung. */
    public function test_kartu_tanpa_kr_tak_dapat_pengingat_deadline_okr(): void
    {
        $staff = $this->user('staff');
        $this->board();
        $this->kartu(['assigned_to' => $staff->id, 'deadline' => now()->addDay()->toDateString()]);

        $this->actingAs($staff)->get('/kpi')->assertOk();

        $this->assertDatabaseCount('notifications', 0);
    }

    /** KR sumber kartu tanpa board: target wajib diisi manual dan realisasi = kartu
     *  yang tertaut langsung ke KR (bukan seluruh isi board). */
    public function test_kartu_kr_tanpa_board_mengukur_kartu_tautan_langsung(): void
    {
        $owner = $this->user();
        $objective = $this->objective();

        // Tanpa board & tanpa target → ditolak server.
        $this->actingAs($owner)->post('/okr/key-results', [
            'objective_id' => $objective->id,
            'title' => 'Selesaikan modul penjualan',
            'source' => 'kartu',
            'unit' => 'angka',
        ])->assertSessionHasErrors('target');

        // Target diisi manual → diterima, board_key null, target tersimpan.
        $this->post('/okr/key-results', [
            'objective_id' => $objective->id,
            'title' => 'Selesaikan modul penjualan',
            'source' => 'kartu',
            'unit' => 'angka',
            'target' => 5,
        ])->assertSessionHasNoErrors();

        $kr = KeyResult::firstOrFail();
        $this->assertSame('kartu', $kr->source);
        $this->assertNull($kr->board_key);
        $this->assertSame('5.00', $kr->target);

        // Tautkan kartu & selesaikan sebagian — realisasi ikut bergerak.
        $done = $this->kartu([
            'category' => 'proyek', 'endorse' => 'Langka 1',
            'key_result_id' => $kr->id, 'completed_at' => now(),
        ]);
        $this->kartu([
            'category' => 'proyek', 'endorse' => 'Langka 2',
            'key_result_id' => $kr->id,
        ]);

        // realisasi = kartu tertaut yang selesai
        $this->assertSame(1.0, $kr->actual());
        // 1/5 = 20%
        $this->assertSame(20.0, $kr->percent());

        $this->actingAs($this->user())->get('/okr')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('objectives.0.key_results.0.actual', 1));
    }

    /** Mengganti kolom ke "Selesai" lewat modal edit Kartu HARUS men-stempel
     *  completed_at — selama ini jalur ini luput, padahal itulah cara paling
     *  lazim user menandai pekerjaan rampung. Tanpa stempel, statistik
     *  ketepatan & target progress board tidak akan bergerak. */
    public function test_edit_kartu_ganti_kolom_ke_selesai_menstempel_waktu(): void
    {
        $user = $this->user();
        $board = $this->board('lms');
        $kolomSelesai = BoardColumn::where('board_key', 'lms')->orderBy('position')->get()->last()->key;

        $kartu = $this->kartu([
            'category' => 'lms',
            'endorse' => 'Bangun modul kursus',
            'progress' => 'todo',
            'deadline' => now()->addDays(7)->toDateString(),
        ]);

        // Sebelum: completed_at null, ketepatan tidak bisa dinilai.
        $this->assertNull($kartu->completed_at);
        $this->assertNull($kartu->ketepatan());

        // User membuka modal edit, pilih kolom "Selesai", simpan.
        $this->actingAs($user)->put("/pipelines/{$kartu->id}", [
            'category' => 'lms',
            'account' => 'fk',
            'payment_status' => 'belum',
            'endorse' => 'Bangun modul kursus',
            'progress' => $kolomSelesai,
            'deadline' => now()->addDays(7)->toDateString(),
        ])->assertRedirect();

        $kartu->refresh();
        $this->assertNotNull($kartu->completed_at, 'completed_at harus terstempel saat pindah ke kolom terakhir lewat edit');
        $this->assertSame('tepat', $kartu->ketepatan());

        // Statistik board ikut berubah pada kunjungan halaman berikutnya.
        $target = BoardQuarterTarget::updateOrCreate(
            ['board_key' => 'lms', 'year' => now()->year, 'quarter' => Quarter::current()['quarter']],
            ['target_done' => 10, 'created_by' => $user->id],
        );

        $this->actingAs($user)
            ->get('/pipelines/kanban?category=lms')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('quarterStats.done', 1)
                ->where('quarterStats.ketepatan.tepat', 1)
                ->where('quarterStats.ketepatan.terlambat', 0)
            );
    }

    /** Kartu dengan deadline lewat → diselesaikan sekarang → ketepatannya
     *  "terlambat", bukan "tepat". */
    public function test_kartu_selesai_terlambat_dihitung_terlambat_di_statistik(): void
    {
        $user = $this->user();
        $board = $this->board('lms');
        $kolomSelesai = BoardColumn::where('board_key', 'lms')->orderBy('position')->get()->last()->key;

        $kartu = $this->kartu([
            'category' => 'lms',
            'endorse' => 'Perbaiki bug login',
            'progress' => 'todo',
            'deadline' => now()->subDays(3)->toDateString(),  // 3 hari lalu
        ]);

        $this->actingAs($user)->put("/pipelines/{$kartu->id}", [
            'category' => 'lms',
            'account' => 'fk',
            'payment_status' => 'belum',
            'endorse' => 'Perbaiki bug login',
            'progress' => $kolomSelesai,
            'deadline' => now()->subDays(3)->toDateString(),
        ])->assertRedirect();

        $this->assertNotNull($kartu->fresh()->completed_at);
        $this->assertSame('terlambat', $kartu->fresh()->ketepatan());

        BoardQuarterTarget::updateOrCreate(
            ['board_key' => 'lms', 'year' => now()->year, 'quarter' => Quarter::current()['quarter']],
            ['target_done' => 10, 'created_by' => $user->id],
        );

        $this->actingAs($user)
            ->get('/pipelines/kanban?category=lms')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('quarterStats.done', 1)
                ->where('quarterStats.ketepatan.tepat', 0)
                ->where('quarterStats.ketepatan.terlambat', 1)
            );
    }

    /** Mengganti kolom KELUAR dari Selesai HARUS mencabut completed_at —
     *  kartu yang dibuka lagi tak boleh menyisakan stempel lama. */
    public function test_edit_kartu_keluar_dari_kolom_selesai_mencabut_stempel(): void
    {
        $user = $this->user();
        $board = $this->board('lms');
        $kolomSelesai = BoardColumn::where('board_key', 'lms')->orderBy('position')->get()->last()->key;

        $kartu = $this->kartu([
            'category' => 'lms',
            'endorse' => 'Tugas yang dibuka lagi',
            'progress' => $kolomSelesai,
            'completed_at' => now(),
            'deadline' => now()->toDateString(),
        ]);
        $this->assertNotNull($kartu->completed_at);

        $this->actingAs($user)->put("/pipelines/{$kartu->id}", [
            'category' => 'lms',
            'account' => 'fk',
            'payment_status' => 'belum',
            'endorse' => 'Tugas yang dibuka lagi',
            'progress' => 'todo',
            'deadline' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertNull($kartu->fresh()->completed_at, 'completed_at harus dicabut saat keluar dari kolom terakhir');
    }
}
