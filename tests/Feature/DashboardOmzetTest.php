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

    private function deal(string $account, float $idr, float $usd = 0): Pipeline
    {
        return Pipeline::create([
            'category' => 'sales', 'account' => $account, 'endorse' => 'Deal '.$account,
            'progress' => 'lead', 'payment_status' => 'belum',
            'amount_idr' => $idr, 'amount_usd' => $usd,
        ]);
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
