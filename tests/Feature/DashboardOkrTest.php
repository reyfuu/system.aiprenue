<?php

namespace Tests\Feature;

use App\Models\KeyResult;
use App\Models\Objective;
use App\Models\User;
use App\Support\Quarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Kartu OKR di Dashboard — ringkasan target kuartal BERJALAN.
 *
 *  Kartunya menaut ke /okr, jadi angkanya wajib memakai aturan yang sama
 *  persis dgn halaman itu (ambang on track ≥60%, KR tanpa target diabaikan).
 *  Dua halaman yang menyebut objective yang sama dgn status berbeda lebih
 *  buruk daripada tak ada kartunya sama sekali.
 *
 *  Yang dijaga tes ini: isinya benar, batas kuartalnya benar, dan datanya
 *  TIDAK terkirim ke peran yang tak boleh melihat OKR.
 */
class DashboardOkrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // DashboardController memanggil ExchangeRate::usdToIdr(). Diisi di sini
        // supaya tes tak bergantung pada kurs dari luar — kartu OKR sendiri tak
        // memakai kurs sama sekali.
        Cache::put('usd_idr_rate', 16000.0);
    }

    /** Objective di kuartal berjalan, kecuali dioper kuartal lain. */
    private function objective(string $title, ?int $year = null, ?int $quarter = null): Objective
    {
        $q = Quarter::current();

        return Objective::create([
            'year' => $year ?? $q['year'],
            'quarter' => $quarter ?? $q['quarter'],
            'title' => $title,
        ]);
    }

    /** KR manual: realisasinya angka tetap, jadi persennya tak bergantung pada
     *  isi Insight/Pembukuan — tes ini menguji kartu, bukan OkrMetrics. */
    private function kr(Objective $o, float $target, ?float $actual): KeyResult
    {
        return KeyResult::create([
            'objective_id' => $o->id, 'title' => 'KR '.$o->title,
            'source' => 'manual', 'target' => $target, 'actual_manual' => $actual, 'unit' => 'angka',
        ]);
    }

    /** Prop `okr` dari halaman Dashboard untuk peran tertentu. */
    private function propOkr(string $role = 'owner', string $url = '/dashboard'): mixed
    {
        $props = null;
        $this->actingAs(User::factory()->create(['role' => $role]))->get($url)
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        return $props['okr'];
    }

    /** Angka pokok kartu: rata-rata progress, cacah objective & KR, dan
     *  pemilahan on track / at risk pada ambang 60%. */
    public function test_kartu_okr_meringkas_objective_kuartal_berjalan(): void
    {
        $this->kr($this->objective('Hampir tercapai'), 10, 9);   // 90% → on track
        $this->kr($this->objective('Tertinggal'), 10, 3);        // 30% → at risk

        $okr = $this->propOkr();

        $this->assertSame(2, $okr['objectives']);
        $this->assertSame(2, $okr['keyResults']);
        $this->assertSame(60.0, (float) $okr['progress'], 'rata-rata (90 + 30) / 2');
        $this->assertSame(1, $okr['onTrack']);
        $this->assertSame(1, $okr['atRisk']);
        $this->assertSame(Quarter::label(...array_values(Quarter::current())), $okr['label']);
    }

    /**
     * Objective tanpa target sama sekali tak boleh terhitung "at risk".
     *
     *  "Belum ditetapkan" bukan "belum tercapai" — aturan yang sama dgn
     *  Objective::progress(). Kalau ikut terhitung, kuartal yang baru dibuka
     *  (targetnya belum diisi) akan selalu terbaca merah di dashboard.
     */
    public function test_objective_tanpa_target_tak_dihitung_at_risk(): void
    {
        $this->kr($this->objective('Bertarget'), 10, 10);
        $this->kr($this->objective('Belum bertarget'), 0, null);

        $okr = $this->propOkr();

        $this->assertSame(2, $okr['objectives'], 'tetap ikut dicacah');
        $this->assertSame(100.0, (float) $okr['progress'], 'hanya yang bertarget masuk rata-rata');
        $this->assertSame(1, $okr['onTrack']);
        $this->assertSame(0, $okr['atRisk']);
    }

    /**
     * Sorotan = tiga progress TERENDAH, yang belum bisa dinilai paling belakang.
     *
     *  Dashboard dipakai untuk memutuskan apa yang perlu ditengok, jadi yang
     *  ditampilkan adalah objective yang tertinggal — bukan yang paling bagus.
     *  Objective tanpa target ditaruh di belakang supaya tak menyerobot tempat
     *  objective yang benar-benar merah.
     */
    public function test_sorotan_dimulai_dari_progress_terendah(): void
    {
        $this->kr($this->objective('Bagus'), 10, 10);          // 100%
        $this->kr($this->objective('Sedang'), 10, 7);          // 70%
        $this->kr($this->objective('Paling merah'), 10, 1);    // 10%
        $this->kr($this->objective('Tanpa target'), 0, null);  // null

        $okr = $this->propOkr();

        $this->assertCount(3, $okr['sorot'], 'kartu dashboard hanya memuat tiga');
        $this->assertSame(
            ['Paling merah', 'Sedang', 'Bagus'],
            array_column($okr['sorot'], 'title'),
            'urut menaik; objective tanpa target tak menyerobot tiga besar'
        );
    }

    /** Kuartal lain tak boleh ikut: kartu ini bicara "kuartal ini sejauh mana". */
    public function test_objective_kuartal_lain_tidak_ikut(): void
    {
        $q = Quarter::current();
        $lalu = $q['quarter'] === 1
            ? ['year' => $q['year'] - 1, 'quarter' => 4]
            : ['year' => $q['year'], 'quarter' => $q['quarter'] - 1];

        $this->kr($this->objective('Kuartal ini'), 10, 5);
        $this->kr($this->objective('Kuartal lalu', $lalu['year'], $lalu['quarter']), 10, 10);

        $okr = $this->propOkr();

        $this->assertSame(1, $okr['objectives']);
        $this->assertSame(50.0, (float) $okr['progress'], 'objective kuartal lalu tak menaikkan angka');
    }

    /**
     * Filter bulan di Dashboard milik Order, bukan OKR.
     *
     *  OKR hidup per kuartal; memaksanya ikut filter bulan hanya akan
     *  memunculkan angka yang tak pernah cocok dgn halaman /okr.
     */
    public function test_filter_bulan_tidak_mengubah_angka_okr(): void
    {
        $this->kr($this->objective('Tetap'), 10, 4);

        $tanpaFilter = $this->propOkr('owner');
        $denganFilter = $this->propOkr('owner', '/dashboard?bulan=2026-01');

        $this->assertSame($tanpaFilter['progress'], $denganFilter['progress']);
        $this->assertSame($tanpaFilter['objectives'], $denganFilter['objectives']);
    }

    /** Manager ikut boleh — audiens OKR = owner + manager (User::canSee). */
    public function test_manager_ikut_menerima_kartu_okr(): void
    {
        $this->kr($this->objective('Target bersama'), 10, 8);

        $this->assertSame(80.0, (float) $this->propOkr('manager')['progress']);
    }

    /**
     * Peran tanpa akses OKR tak menerima angkanya SAMA SEKALI.
     *
     *  Bukan sekadar kartunya disembunyikan di Vue: prop-nya harus null, sebab
     *  props Inertia terkirim utuh ke browser.
     *
     *  Peran 'it' dipakai karena ia satu-satunya peran yang boleh membuka
     *  Dashboard TAPI tak boleh melihat OKR — peran lain (admin, editor, staff)
     *  sudah tertahan 403 di pintu Dashboard, jadi tak bisa membuktikan apa pun
     *  tentang isi prop-nya.
     */
    public function test_peran_tanpa_akses_okr_tidak_menerima_angkanya(): void
    {
        $this->kr($this->objective('Rahasia perusahaan'), 10, 9);

        $this->assertNull($this->propOkr('it'));
    }

    /** Kuartal yang belum berisi apa pun: kartu tetap tampil dgn progress null
     *  (bukan 0%) — "belum ada target" beda arti dgn "target tak tercapai". */
    public function test_kuartal_kosong_memberi_progress_null_bukan_nol(): void
    {
        $okr = $this->propOkr();

        $this->assertSame(0, $okr['objectives']);
        $this->assertNull($okr['progress']);
        $this->assertSame([], $okr['sorot']);
    }
}
