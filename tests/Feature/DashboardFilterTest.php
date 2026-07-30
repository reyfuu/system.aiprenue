<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Filter periode di Dashboard: Grand Omzet & kartu Order bisa dilihat per bulan. */
class DashboardFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Kurs di-cache supaya tes tak memanggil open.er-api.com (lambat & flaky).
        Cache::put('usd_idr_rate', 16000.0);
    }

    private function order(string $tanggalBayar, float $idr, string $account = 'fk'): Order
    {
        return Order::create([
            'tipe_order'      => 'endorse',
            'account'         => $account,
            'nama_customer'   => 'Pelanggan',
            'tipe_pembayaran' => 'full',
            'tanggal_bayar'   => $tanggalBayar,
            'total_idr'       => $idr,
        ]);
    }

    public function test_tanpa_filter_menampilkan_semua_bulan(): void
    {
        $this->order('2026-05-10', 1_000_000);
        $this->order('2026-06-10', 2_000_000);

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('filter.bulan', 'semua')
                ->where('summary.grandIdr', 3_000_000)
                ->where('order.total', 2)
            );
    }

    public function test_filter_bulan_mempersempit_grand_omzet(): void
    {
        $this->order('2026-05-10', 1_000_000);
        $this->order('2026-06-10', 2_000_000);
        $this->order('2026-06-20', 500_000);

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/dashboard?bulan=2026-06')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('filter.bulan', '2026-06')
                ->where('summary.grandIdr', 2_500_000)   // Mei tak ikut
                ->where('order.total', 2)
            );
    }

    /** USD wajib ikut dikonversi di dalam bulan terfilter — kalau tidak, order USD
     *  terlihat bernilai nol pada tampilan per bulan. */
    public function test_usd_ikut_dikonversi_pada_bulan_terfilter(): void
    {
        Order::create([
            'tipe_order' => 'endorse', 'account' => 'fk', 'nama_customer' => 'Bule',
            'tipe_pembayaran' => 'full', 'tanggal_bayar' => '2026-06-05',
            'total_idr' => 0, 'total_usd' => 100,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/dashboard?bulan=2026-06')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('summary.grandIdr', 1_600_000));  // 100 × 16.000
    }

    /** Pecahan per akun harus ikut menyempit juga — kalau tidak, FK + AI Preneur
     *  tak lagi berjumlah Grand Omzet & angkanya saling bertentangan di layar. */
    public function test_omzet_per_akun_ikut_terfilter(): void
    {
        $this->order('2026-05-10', 1_000_000, 'fk');
        $this->order('2026-06-10', 2_000_000, 'fk');
        $this->order('2026-06-11', 3_000_000, 'ai_preneur');

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/dashboard?bulan=2026-06')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('summary.perAccount.fk.grandIdr', 2_000_000)
                ->where('summary.perAccount.ai_preneur.grandIdr', 3_000_000)
                ->where('summary.grandIdr', 5_000_000)   // = penjumlahan keduanya
            );
    }

    /** Grafik tren SENGAJA tetap semua bulan: kalau ikut terfilter, isinya tinggal
     *  satu titik & kehilangan gunanya sbg pembanding antar bulan. */
    public function test_grafik_tetap_menampilkan_semua_bulan(): void
    {
        $this->order('2026-05-10', 1_000_000);
        $this->order('2026-06-10', 2_000_000);

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/dashboard?bulan=2026-06')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('monthly', 2));
    }

    /** Bulan ngawur jangan bikin halaman kosong diam-diam — jatuh ke 'semua'. */
    public function test_bulan_tak_dikenal_jatuh_ke_semua(): void
    {
        $this->order('2026-05-10', 1_000_000);

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/dashboard?bulan=1999-13')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('filter.bulan', 'semua')
                ->where('summary.grandIdr', 1_000_000)
            );
    }

    /** Dropdown cuma memuat bulan yang benar-benar punya order. */
    public function test_opsi_bulan_hanya_yang_ada_datanya(): void
    {
        $this->order('2026-05-10', 1_000_000);
        $this->order('2026-06-10', 2_000_000);

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('filter.opsi', 2)
                ->where('filter.opsi.0.value', '2026-06')   // terbaru dulu
            );
    }

    /** Order tanpa tanggal_bayar mundur ke created_at — jangan sampai lenyap
     *  dari filter & bikin jumlah per bulan tak cocok dgn total. */
    public function test_order_tanpa_tanggal_bayar_pakai_created_at(): void
    {
        $o = Order::create([
            'tipe_order' => 'endorse', 'account' => 'fk', 'nama_customer' => 'Tanpa tanggal',
            'tipe_pembayaran' => 'dp', 'total_idr' => 750_000,
        ]);
        $bulan = $o->created_at->format('Y-m');

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get("/dashboard?bulan={$bulan}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('summary.grandIdr', 750_000));
    }
}
