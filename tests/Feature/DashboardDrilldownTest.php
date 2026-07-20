<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Drill-down Dashboard: klik kartu ringkasan → tabel order menggantikan grafik.
 *
 * Yang paling penting diuji bukan "tabelnya muncul", tapi bahwa jumlah baris
 * SELALU sama dengan angka di kartu yang diklik — termasuk saat filter bulan
 * aktif. Kalau dua-duanya berselisih, dashboardnya berhenti bisa dipercaya.
 */
class DashboardDrilldownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('usd_idr_rate', 16000.0);
    }

    private function order(string $tanggalBayar, string $account, string $bayar, float $idr = 1_000_000): Order
    {
        return Order::create([
            'tipe_order'      => 'endorse',
            'account'         => $account,
            'nama_customer'   => 'Pelanggan',
            'tipe_pembayaran' => $bayar,
            'tanggal_bayar'   => $tanggalBayar,
            'total_idr'       => $idr,
        ]);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    /** Tanpa ?lihat → grafik, bukan tabel. */
    public function test_tanpa_parameter_lihat_daftar_kosong(): void
    {
        $this->order('2026-05-10', 'fk', 'full');

        $this->actingAs($this->owner())->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('daftar', null));
    }

    public function test_lihat_dp_hanya_memuat_order_dp(): void
    {
        $this->order('2026-05-10', 'fk', 'dp');
        $this->order('2026-05-11', 'fk', 'dp');
        $this->order('2026-05-12', 'fk', 'full');

        $this->actingAs($this->owner())->get('/dashboard?lihat=dp')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('daftar.kunci', 'dp')
                ->where('daftar.jumlah', 2)
                ->has('daftar.baris', 2)
                ->etc());
    }

    public function test_lihat_akun_hanya_memuat_akun_itu(): void
    {
        $this->order('2026-05-10', 'fk', 'full');
        $this->order('2026-05-11', 'ai_preneur', 'full');

        $this->actingAs($this->owner())->get('/dashboard?lihat=ai_preneur')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('daftar.jumlah', 1)
                ->where('daftar.baris.0.akun', 'AI Preneur')
                ->etc());
    }

    /**
     * Inti fitur ini: angka di kartu = jumlah baris di tabel, WALAU bulan aktif.
     * Drill-down menyaring koleksi $orders yang sudah terfilter bulan. Kalau suatu
     * saat diubah jadi query sendiri tanpa menyalin aturan tanggalnya, tes ini merah.
     */
    public function test_jumlah_baris_cocok_dengan_kartu_saat_bulan_aktif(): void
    {
        $this->order('2026-05-10', 'fk', 'dp');   // di dalam bulan
        $this->order('2026-05-11', 'fk', 'dp');   // di dalam bulan
        $this->order('2026-06-10', 'fk', 'dp');   // DI LUAR bulan — tak boleh ikut

        $this->actingAs($this->owner())->get('/dashboard?bulan=2026-05&lihat=dp')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('summary.outstanding', 2)   // angka di kartu
                ->where('daftar.jumlah', 2)         // jumlah baris — WAJIB sama
                ->has('daftar.baris', 2)
                ->etc());
    }

    /**
     * `lihat` ngawur harus jatuh ke grafik, BUKAN tabel kosong. Tabel kosong
     * terbaca sebagai "tidak ada datanya", padahal yang salah parameternya.
     */
    public function test_lihat_ngawur_jatuh_ke_grafik(): void
    {
        $this->order('2026-05-10', 'fk', 'full');

        $this->actingAs($this->owner())->get('/dashboard?lihat=sembarang')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('daftar', null));
    }

    /** Kriteria yang sah tapi hasilnya nol tetap menampilkan tabel (kosong), bukan grafik. */
    public function test_kriteria_sah_tanpa_hasil_tetap_menampilkan_tabel(): void
    {
        $this->order('2026-05-10', 'fk', 'full');   // tak ada satu pun DP

        $this->actingAs($this->owner())->get('/dashboard?lihat=dp')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('daftar.jumlah', 0)
                ->has('daftar.baris', 0)
                ->etc());
    }

    /**
     * Tombol Grand Omzet menutup drill-down TANPA mereset periode. Klien mengirim
     * `bulan` tanpa `lihat`; kalau bulan ikut hilang, angka melonjak ke seluruh
     * waktu dan orang mengira datanya berubah sendiri.
     */
    public function test_menutup_daftar_tidak_mereset_periode(): void
    {
        $this->order('2026-05-10', 'fk', 'dp');
        $this->order('2026-06-10', 'fk', 'dp');

        $this->actingAs($this->owner())->get('/dashboard?bulan=2026-05')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('daftar', null)              // grafik kembali
                ->where('filter.bulan', '2026-05')   // periode bertahan
                ->where('summary.outstanding', 1)
                ->etc());
    }

    /** Panel dibatasi 50 baris, tapi `jumlah` tetap melaporkan angka sebenarnya
     *  supaya UI bisa berkata "menampilkan 50 dari sekian". */
    public function test_baris_dibatasi_50_tapi_jumlah_melaporkan_total_sebenarnya(): void
    {
        foreach (range(1, 55) as $i) {
            $this->order('2026-05-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT), 'fk', 'full');
        }

        $this->actingAs($this->owner())->get('/dashboard?lihat=all')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('daftar.jumlah', 55)
                ->has('daftar.baris', 50)
                ->etc());
    }
}
