<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Category;
use App\Models\Pipeline;
use App\Models\User;
use App\Support\KinerjaOrang;
use App\Support\Quarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rapor kinerja per orang di halaman KPI (tab "Per Orang"). */
class KpiOrangTest extends TestCase
{
    use RefreshDatabase;

    private array $q;

    private string $tgl;      // tanggal deadline di dalam kuartal berjalan

    protected function setUp(): void
    {
        parent::setUp();

        $this->q = Quarter::current();
        [$start] = Quarter::range($this->q['year'], $this->q['quarter']);
        $this->tgl = $start->addDays(5)->toDateString();

        Category::create(['key' => 'proyek', 'name' => 'Proyek', 'type' => 'kanban']);
        foreach (['todo', 'progress', 'done'] as $i => $col) {
            BoardColumn::create(['board_key' => 'proyek', 'key' => $col, 'name' => $col, 'color' => 'bg-slate-400', 'position' => $i]);
        }
    }

    private function user(string $role, string $nama): User
    {
        return User::factory()->create(['role' => $role, 'name' => $nama]);
    }

    /** $selesaiHari = hari ke berapa dari awal kuartal kartu diselesaikan
     *  (null = belum selesai). Deadline selalu hari ke-5. */
    private function kartu(?User $pj, ?int $selesaiHari): Pipeline
    {
        [$start] = Quarter::range($this->q['year'], $this->q['quarter']);

        return Pipeline::create([
            'category' => 'proyek', 'endorse' => 'Task', 'account' => 'fk',
            'progress' => 'todo', 'payment_status' => 'belum',
            'assigned_to' => $pj?->id,
            'deadline' => $this->tgl,
            'completed_at' => $selesaiHari === null ? null : $start->addDays($selesaiHari),
        ]);
    }

    // ------------------------------------------------------------ isi rapor

    public function test_kartu_dikelompokkan_per_penanggung_jawab(): void
    {
        $budi = $this->user('staff', 'Budi');
        $sari = $this->user('admin', 'Sari');

        $this->kartu($budi, 3);      // tepat waktu
        $this->kartu($budi, 9);      // terlambat 4 hari
        $this->kartu($sari, 4);      // tepat waktu

        $baris = collect(KinerjaOrang::untukKuartal($this->q['year'], $this->q['quarter']));

        $this->assertSame(2, $baris->firstWhere('nama', 'Budi')['total']);
        $this->assertSame(1, $baris->firstWhere('nama', 'Budi')['tepat']);
        $this->assertSame(1, $baris->firstWhere('nama', 'Budi')['terlambat']);
        $this->assertSame(1, $baris->firstWhere('nama', 'Sari')['total']);
    }

    /** Kartu tanpa PJ tak boleh hilang: kalau dibuang, jumlah kartu di rapor
     *  tak akan pernah cocok dgn jumlah kartu di board & selisihnya jadi
     *  misteri tanpa penjelasan di layar. */
    public function test_kartu_tanpa_pj_masuk_baris_belum_ditugaskan(): void
    {
        $this->kartu(null, null);
        $this->kartu(null, 3);

        $baris = collect(KinerjaOrang::untukKuartal($this->q['year'], $this->q['quarter']));
        $tanpa = $baris->firstWhere('user_id', null);

        $this->assertNotNull($tanpa);
        $this->assertSame('Belum ditugaskan', $tanpa['nama']);
        $this->assertSame(2, $tanpa['total']);
    }

    /** User tanpa kartu tidak dirender — baris nol di semua kolom hanya jadi
     *  kebisingan dan menenggelamkan yang benar-benar bekerja. */
    public function test_user_tanpa_kartu_tidak_muncul(): void
    {
        $this->user('staff', 'Nganggur');
        $aktif = $this->user('staff', 'Aktif');
        $this->kartu($aktif, 3);

        $nama = collect(KinerjaOrang::untukKuartal($this->q['year'], $this->q['quarter']))->pluck('nama');

        $this->assertContains('Aktif', $nama);
        $this->assertNotContains('Nganggur', $nama);
    }

    /**
     * Rata-rata keterlambatan HANYA dari kartu yang terlambat.
     *
     *  Kartu yang selesai lebih awal bernilai negatif; kalau ikut dihitung,
     *  seseorang yang telat 10 hari sekali tapi sering selesai awal akan
     *  terbaca "rata-rata tepat waktu" — jawaban yang salah untuk pertanyaan
     *  "kalau telat, biasanya telat berapa lama".
     */
    public function test_rata_rata_keterlambatan_abaikan_kartu_yang_selesai_lebih_awal(): void
    {
        $budi = $this->user('staff', 'Budi');
        $this->kartu($budi, 0);      // selesai 5 hari SEBELUM deadline
        $this->kartu($budi, 15);     // terlambat 10 hari

        $baris = collect(KinerjaOrang::untukKuartal($this->q['year'], $this->q['quarter']))->firstWhere('nama', 'Budi');

        // 10, bukan (10 + (-5)) / 2 = 2,5.
        $this->assertSame(10.0, $baris['rata_telat']);
    }

    /** Tak pernah telat → null, bukan 0 hari. Dua hal itu beda arti dan UI
     *  menampilkannya berbeda ('—' vs '0 hari'). */
    public function test_tak_pernah_telat_menghasilkan_null_bukan_nol(): void
    {
        $budi = $this->user('staff', 'Budi');
        $this->kartu($budi, 3);

        $baris = collect(KinerjaOrang::untukKuartal($this->q['year'], $this->q['quarter']))->firstWhere('nama', 'Budi');

        $this->assertNull($baris['rata_telat']);
    }

    // ------------------------------------------------------------ hak akses

    /** Inti pemisahan: staff hanya menerima BARISNYA SENDIRI. Yang diuji bukan
     *  "nama rekan tak tampil", tapi "nama rekan tak dikirim" — props Inertia
     *  terbaca utuh di source halaman. */
    public function test_staff_hanya_menerima_barisnya_sendiri(): void
    {
        $budi = $this->user('staff', 'Budi');
        $rahasia = $this->user('manager', 'Rani');
        $this->kartu($budi, 3);
        $this->kartu($rahasia, 9);

        $this->actingAs($budi)->get('/kpi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('scope', 'sendiri')
                ->has('orang', 1)
                ->where('orang.0.nama', 'Budi')
                ->where('board', null)        // tab Per Board tak dikirim sama sekali
            );
    }

    /** Peran pengelola non-atasan (it/admin) boleh melihat tab Board, tapi
     *  tetap hanya barisnya sendiri di Per Orang. */
    public function test_it_melihat_board_tapi_hanya_rapor_dirinya(): void
    {
        $audi = $this->user('it', 'Audi');
        $lain = $this->user('staff', 'Budi');
        $this->kartu($audi, 3);
        $this->kartu($lain, 3);

        $this->actingAs($audi)->get('/kpi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('scope', 'sendiri')
                ->has('orang', 1)
                ->where('orang.0.nama', 'Audi')
                ->has('board')
            );
    }

    public function test_owner_dan_manager_melihat_seluruh_tim(): void
    {
        $budi = $this->user('staff', 'Budi');
        $this->kartu($budi, 3);

        foreach ([$this->user('owner', 'Bos'), $this->user('manager', 'Rani')] as $atasan) {
            $this->kartu($atasan, 3);

            $this->actingAs($atasan)->get('/kpi')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('scope', 'semua')
                    ->has('board')
                );
        }

        // Nama staff memang terbaca oleh atasan — itu gunanya rapor ini.
        $this->actingAs(User::where('name', 'Bos')->first())->get('/kpi')
            ->assertSee('Budi', false);
    }

    /** Staff yang belum pegang kartu tetap boleh membuka halamannya; yang
     *  diterima cuma daftar kosong, bukan 403. */
    public function test_staff_tanpa_kartu_tetap_boleh_membuka_halaman(): void
    {
        $this->actingAs($this->user('staff', 'Baru'))->get('/kpi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('orang', 0));
    }
}
