<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Ringkasan atas & grafik Dashboard bersumber ORDER (omzet nyata), BUKAN Sales
 *  (corong prospek — nilainya estimasi & bisa batal). Alur: sales → order → kanban. */
class DashboardOmzetTest extends TestCase
{
    use RefreshDatabase;

    private const RATE = 16250.5;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::put('usd_idr_rate', self::RATE);
    }

    private function order(string $account, float $idr, float $usd = 0, ?string $bayar = null, string $bayarTipe = 'full'): Order
    {
        return Order::create([
            'tipe_order' => 'endorse', 'account' => $account,
            'nama_customer' => 'Cust '.uniqid(), 'telepon' => '08123456789',
            'kota' => 'Kota Bandung', 'tanggal_deadline' => '2026-12-01',
            'tipe_pembayaran' => $bayarTipe, 'tanggal_bayar' => $bayar,
            'total_idr' => $idr, 'total_usd' => $usd,
        ]);
    }

    /** Kartu Sales dipakai sbg pembanding: angkanya TIDAK boleh bocor ke ringkasan atas. */
    private function dealSales(float $idr): Pipeline
    {
        return Pipeline::create([
            'category' => 'sales', 'account' => 'fk', 'endorse' => 'Deal '.uniqid(),
            'progress' => 'lead', 'payment_status' => 'belum', 'amount_idr' => $idr,
        ]);
    }

    private function props(): array
    {
        $props = null;
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get('/dashboard')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        return $props;
    }

    /** Inti koreksinya: omzet = Order, bukan Sales. */
    public function test_ringkasan_atas_dari_order_bukan_dari_sales(): void
    {
        $this->order('fk', 100_000_000);
        $this->dealSales(999_000_000);   // deal Sales besar — harus DIABAIKAN ringkasan atas

        $props = $this->props();

        $this->assertSame(100_000_000, (int) $props['summary']['grandIdr'], 'Grand Omzet harus dari Order');
        $this->assertSame(1, $props['summary']['total'], 'Total Order, bukan jumlah entri Sales');
        // kartu modul Sales tetap punya angkanya sendiri (estimasi corong)
        $this->assertSame(999_000_000, (int) $props['pipeline']['grandIdr']);
    }

    public function test_omzet_fk_dan_ai_preneur_dipecah_dan_ikut_kurs(): void
    {
        $this->order('fk', 10_000_000);
        $this->order('fk', 0, 100.5);            // 100,5 × 16.250,5 = 1.633.175,25
        $this->order('ai_preneur', 5_000_000);

        $props = $this->props();
        $akun = $props['summary']['perAccount'];

        $this->assertSame('FK', $akun['fk']['label']);
        $this->assertEqualsWithDelta(11_633_175.25, $akun['fk']['grandIdr'], 0.01);
        $this->assertSame(2, $akun['fk']['total']);
        $this->assertSame('AI Preneur', $akun['ai_preneur']['label']);
        $this->assertSame(5_000_000, (int) $akun['ai_preneur']['grandIdr']);
        $this->assertSame(1, $akun['ai_preneur']['total']);
    }

    /** Sifat yang menjaga kartunya jujur: FK + AI Preneur HARUS = Grand Omzet.
     *  Kalau tidak, salah satunya menghitung dari sumber yang berbeda. */
    public function test_jumlah_kartu_akun_selalu_sama_dengan_grand_omzet(): void
    {
        $this->order('fk', 7_500_000, 42.5);
        $this->order('ai_preneur', 3_250_000, 17.25);
        $this->order('fk', 1_000_000);

        $props = $this->props();
        $jumlah = array_sum(array_column($props['summary']['perAccount'], 'grandIdr'));

        $this->assertEqualsWithDelta($props['summary']['grandIdr'], $jumlah, 0.01,
            'FK + AI Preneur harus = Grand Omzet — kalau tidak, ada akun yang tak terhitung');
    }

    public function test_akun_tanpa_order_tetap_tampil_nol(): void
    {
        $this->order('fk', 1_000_000);

        $akun = $this->props()['summary']['perAccount'];

        $this->assertSame(0, (int) $akun['ai_preneur']['grandIdr']);
        $this->assertSame(0, $akun['ai_preneur']['total']);
    }

    /** Order cuma kenal full/dp — tak ada status 'belum' seperti kartu Sales. */
    public function test_lunas_dan_outstanding_dari_tipe_pembayaran_order(): void
    {
        $this->order('fk', 1_000_000, 0, null, 'full');
        $this->order('fk', 2_000_000, 0, null, 'full');
        $this->order('ai_preneur', 3_000_000, 0, null, 'dp');

        $props = $this->props();

        $this->assertSame(3, $props['summary']['total']);
        $this->assertSame(2, $props['summary']['lunas'], 'lunas = tipe_pembayaran full');
        $this->assertSame(1, $props['summary']['outstanding'], 'outstanding = tipe_pembayaran dp');
    }

    public function test_grafik_dikelompokkan_per_bulan_pakai_tanggal_bayar(): void
    {
        $this->order('fk', 10_000_000, 0, '2026-06-05');
        $this->order('ai_preneur', 4_000_000, 0, '2026-06-20');
        $this->order('fk', 7_000_000, 0, '2026-07-02');

        $bulan = $this->props()['monthly'];

        $this->assertCount(2, $bulan, 'Juni & Juli → dua titik');
        $this->assertSame(['Jun 2026', 'Jul 2026'], array_column($bulan, 'label'), 'urut kronologis');

        $this->assertSame(10_000_000, $bulan[0]['perAccount']['fk']);
        $this->assertSame(4_000_000, $bulan[0]['perAccount']['ai_preneur']);
        $this->assertSame(14_000_000, $bulan[0]['total']);
        $this->assertSame(0, $bulan[1]['perAccount']['ai_preneur'], 'akun tanpa order bulan itu = 0, bukan hilang');
    }

    /** Grafik pakai tanggal_bayar (kapan uang masuk), BUKAN tanggal_deadline. */
    public function test_grafik_pakai_tanggal_bayar_bukan_deadline(): void
    {
        // deadline Desember, tapi bayarnya Juni → grafik harus bilang Juni
        Order::create([
            'tipe_order' => 'endorse', 'account' => 'fk', 'nama_customer' => 'Cust',
            'telepon' => '08123456789', 'kota' => 'Kota Bandung',
            'tanggal_deadline' => '2026-12-25', 'tipe_pembayaran' => 'full',
            'tanggal_bayar' => '2026-06-10', 'total_idr' => 5_000_000, 'total_usd' => 0,
        ]);

        $bulan = $this->props()['monthly'];

        $this->assertCount(1, $bulan);
        $this->assertSame('Jun 2026', $bulan[0]['label']);
    }

    /** Order tanpa tanggal_bayar jangan lenyap — mundur ke created_at. */
    public function test_order_tanpa_tanggal_bayar_tetap_masuk_grafik(): void
    {
        $this->order('fk', 5_000_000);   // tanggal_bayar null

        $bulan = $this->props()['monthly'];

        $this->assertCount(1, $bulan, 'order tanpa tanggal_bayar tetap dapat titik (via created_at)');
        $this->assertSame(5_000_000, $bulan[0]['total']);
        $this->assertSame(now()->translatedFormat('M Y'), $bulan[0]['label']);
    }

    /** Sifat yang menjaga grafiknya jujur: jumlah SEMUA bulan = Grand Omzet.
     *  Kalau tidak, ada order yang tak masuk grafik tanpa jejak. */
    public function test_jumlah_semua_bulan_sama_dengan_grand_omzet(): void
    {
        $this->order('fk', 3_000_000, 12.5, '2026-05-11');
        $this->order('ai_preneur', 2_000_000, 0, '2026-06-01');
        $this->order('fk', 0, 80.25, '2026-07-19');
        $this->order('ai_preneur', 1_500_000);   // tanpa tanggal_bayar → mundur ke created_at

        $props = $this->props();
        $jumlah = array_sum(array_column($props['monthly'], 'total'));

        // delta 1 rupiah: tiap bulan sudah di-round sebelum dijumlah
        $this->assertEqualsWithDelta($props['summary']['grandIdr'], $jumlah, 1.0,
            'jumlah semua bulan harus = Grand Omzet — kalau tidak, ada order yang lolos dari grafik');
    }
}
