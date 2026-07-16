<?php

namespace Tests\Feature;

use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Kartu omzet per akun di Dashboard = pecahan Grand Omzet. */
class DashboardOmzetTest extends TestCase
{
    use RefreshDatabase;

    private const RATE = 16250.5;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::put('usd_idr_rate', self::RATE);
    }

    private function deal(string $account, float $idr, float $usd = 0, ?string $posting = null): Pipeline
    {
        return Pipeline::create([
            'category' => 'sales', 'account' => $account, 'endorse' => 'Deal '.$account.'-'.uniqid(),
            'progress' => 'lead', 'payment_status' => 'belum',
            'amount_idr' => $idr, 'amount_usd' => $usd, 'tanggal_posting' => $posting,
        ]);
    }

    /** @return array<int,array> baris grafik per bulan */
    private function monthly(): array
    {
        $props = null;
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get('/dashboard')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        return $props['monthly'];
    }

    public function test_omzet_dikelompokkan_per_bulan_dan_dipecah_per_akun(): void
    {
        $this->deal('fk', 10_000_000, 0, '2026-06-05');
        $this->deal('ai_preneur', 4_000_000, 0, '2026-06-20');
        $this->deal('fk', 7_000_000, 0, '2026-07-02');

        $bulan = $this->monthly();

        $this->assertCount(2, $bulan, 'Juni & Juli → dua batang');
        // urut kronologis, bukan urutan insert
        $this->assertSame(['Jun 2026', 'Jul 2026'], array_column($bulan, 'label'));

        $this->assertSame(10_000_000, $bulan[0]['perAccount']['fk']);
        $this->assertSame(4_000_000, $bulan[0]['perAccount']['ai_preneur']);
        $this->assertSame(14_000_000, $bulan[0]['total']);

        $this->assertSame(7_000_000, $bulan[1]['perAccount']['fk']);
        $this->assertSame(0, $bulan[1]['perAccount']['ai_preneur'], 'akun tanpa deal bulan itu = 0, bukan hilang');
    }

    /** Sifat yang menjaga grafiknya jujur: jumlah SEMUA bulan = Grand Omzet.
     *  Kalau tidak, ada deal yang tak masuk grafik tanpa jejak. */
    public function test_jumlah_semua_bulan_sama_dengan_grand_omzet(): void
    {
        $this->deal('fk', 3_000_000, 12.5, '2026-05-11');
        $this->deal('ai_preneur', 2_000_000, 0, '2026-06-01');
        $this->deal('fk', 0, 80.25, '2026-07-19');
        $this->deal('ai_preneur', 1_500_000);   // tanpa tanggal_posting → mundur ke created_at

        $props = null;
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get('/dashboard')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        $jumlah = array_sum(array_column($props['monthly'], 'total'));

        // delta 1 rupiah: tiap bulan sudah di-round sebelum dijumlah
        $this->assertEqualsWithDelta($props['summary']['grandIdr'], $jumlah, 1.0,
            'jumlah semua bulan harus = Grand Omzet — kalau tidak, ada deal yang lolos dari grafik');
    }

    /** Deal tanpa tanggal_posting jangan lenyap — mundur ke created_at. Ini alasan
     *  tanggal_payment tak dipakai: cuma terisi saat lunas. */
    public function test_deal_tanpa_tanggal_posting_tetap_masuk_grafik(): void
    {
        $this->deal('fk', 5_000_000);   // tanggal_posting null

        $bulan = $this->monthly();

        $this->assertCount(1, $bulan, 'deal tanpa tanggal_posting tetap dapat batang (via created_at)');
        $this->assertSame(5_000_000, $bulan[0]['total']);
        $this->assertSame(now()->translatedFormat('M Y'), $bulan[0]['label']);
    }

    public function test_omzet_fk_dan_ai_preneur_dipecah_dan_ikut_kurs(): void
    {
        $this->deal('fk', 10_000_000);
        $this->deal('fk', 0, 100.5);            // 100,5 × 16.250,5 = 1.633.175,25
        $this->deal('ai_preneur', 5_000_000);

        $this->actingAs(User::factory()->create(['role' => 'owner']))->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.perAccount.fk.label', 'FK')
                ->where('summary.perAccount.fk.grandIdr', 11_633_175.25)   // pecahan → tetap float
                ->where('summary.perAccount.fk.total', 2)
                ->where('summary.perAccount.ai_preneur.label', 'AI Preneur')
                ->where('summary.perAccount.ai_preneur.grandIdr', 5_000_000) // bulat → JSON kirim int
                ->where('summary.perAccount.ai_preneur.total', 1)
            );
    }

    /** Sifat yang menjaga kartunya jujur: dua kartu itu HARUS berjumlah Grand Omzet.
     *  Kalau tidak, salah satunya menghitung dari sumber yang berbeda. */
    public function test_jumlah_kartu_akun_selalu_sama_dengan_grand_omzet(): void
    {
        $this->deal('fk', 7_500_000, 42.5);
        $this->deal('ai_preneur', 3_250_000, 17.25);
        $this->deal('fk', 1_000_000);

        $this->actingAs(User::factory()->create(['role' => 'owner']))->get('/dashboard')
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $akun = $page->toArray()['props']['summary']['perAccount'];
                $grand = $page->toArray()['props']['summary']['grandIdr'];
                $jumlah = array_sum(array_column($akun, 'grandIdr'));

                $this->assertEqualsWithDelta($grand, $jumlah, 0.01,
                    'FK + AI Preneur harus = Grand Omzet — kalau tidak, ada akun yang tak terhitung');
            });
    }

    /** Akun tanpa deal tetap muncul sebagai kartu Rp 0, bukan hilang dari dashboard. */
    public function test_akun_tanpa_deal_tetap_tampil_nol(): void
    {
        $this->deal('fk', 1_000_000);

        $this->actingAs(User::factory()->create(['role' => 'owner']))->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.perAccount.ai_preneur.grandIdr', 0)
                ->where('summary.perAccount.ai_preneur.total', 0)
            );
    }
}
