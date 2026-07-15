<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private const RATE = 16250.5;

    protected function setUp(): void
    {
        parent::setUp();

        // Total order dihitung pakai kurs (ExchangeRate). Cache di test = array, jadi
        // tanpa ini tiap request benar-benar memanggil open.er-api.com → lambat & flaky.
        \Illuminate\Support\Facades\Cache::put('usd_idr_rate', self::RATE);
    }

    private function user(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /** Payload minimum yang lolos validasi. */
    private function payload(array $override = []): array
    {
        return array_merge([
            'tipe_order'      => 'coaching_1on1',
            'account'         => 'fk',
            'nama_customer'   => 'Budi',
            'tipe_pembayaran' => 'full',
        ], $override);
    }

    public function test_total_pembayaran_adalah_idr_plus_usd_dikonversi(): void
    {
        Order::create($this->payload(['total_idr' => 1_000_000, 'total_usd' => 100.5]));

        // 1.000.000 + 100,5 × 16.250,5 = 2.633.175,25
        $this->actingAs($this->user())->get('/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rate', self::RATE)
                ->where('summary.totalIdr', 1_000_000)     // angka asli tiap mata uang tetap terpisah
                ->where('summary.totalUsd', 100.5)
                ->where('summary.grandIdr', 2_633_175.25)  // dipajang sbg "Total Pembayaran"
            );
    }

    public function test_kurs_nol_akan_menelan_nilai_usd(): void
    {
        // Jaga-jaga: kalau rate jatuh ke 0, order USD-only jadi Rp 0 tanpa suara.
        // ExchangeRate punya fallback 16000, jadi ini harus selalu > 0.
        $this->actingAs($this->user())->get('/orders')
            ->assertInertia(fn (Assert $page) => $page->where('rate', self::RATE));

        $this->assertGreaterThan(0, \App\Support\ExchangeRate::usdToIdr());
    }

    public function test_kota_boleh_diketik_manual_di_luar_dataset_wilayah(): void
    {
        $this->actingAs($this->user())
            ->post('/orders', $this->payload(['kota' => 'Kota Ngawur Bukan Dataset']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Kota Ngawur Bukan Dataset', Order::first()->kota);
    }

    public function test_email_divalidasi(): void
    {
        $this->actingAs($this->user())
            ->post('/orders', $this->payload(['email' => 'bukan-email']))
            ->assertSessionHasErrors('email');

        $this->actingAs($this->user())
            ->post('/orders', $this->payload(['email' => 'budi@example.com']))
            ->assertSessionHasNoErrors();

        $this->assertSame('budi@example.com', Order::first()->email);
    }

    public function test_account_wajib_dan_dibatasi_daftar(): void
    {
        $this->actingAs($this->user())
            ->post('/orders', $this->payload(['account' => 'ngawur']))
            ->assertSessionHasErrors('account');

        $this->assertSame(0, Order::count());
    }

    public function test_prioritas_sudah_tak_ada(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('orders', 'prioritas'));
        $this->assertFalse(defined(Order::class.'::PRIORITAS'));
    }

    public function test_invoice_dan_bukti_bayar_disimpan_terpisah(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user())->post('/orders', $this->payload([
            'bukti_bayar' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
            'invoice'     => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $order = Order::first();
        $this->assertStringStartsWith('bukti-bayar/', $order->bukti_bayar);
        $this->assertStringStartsWith('invoice/', $order->invoice);
        Storage::disk('public')->assertExists([$order->bukti_bayar, $order->invoice]);
    }

    /** Update tanpa file baru tak boleh menghapus file lama. */
    public function test_update_tanpa_file_mempertahankan_berkas_lama(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user())->post('/orders', $this->payload([
            'invoice' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'),
        ]));
        $lama = Order::first()->invoice;

        $this->actingAs($this->user())
            ->put('/orders/'.Order::first()->id, $this->payload(['nama_customer' => 'Budi Diubah']))
            ->assertSessionHasNoErrors();

        $this->assertSame($lama, Order::first()->invoice);
        Storage::disk('public')->assertExists($lama);
    }

    public function test_hapus_order_ikut_membuang_kedua_berkas(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user())->post('/orders', $this->payload([
            'bukti_bayar' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
            'invoice'     => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'),
        ]));

        $order = Order::first();
        [$bukti, $invoice] = [$order->bukti_bayar, $order->invoice];

        $this->actingAs($this->user())->delete('/orders/'.$order->id)->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing([$bukti, $invoice]);
    }

    public function test_staff_tak_boleh_membuat_order(): void
    {
        $this->actingAs($this->user('staff'))->post('/orders', $this->payload())->assertForbidden();
        $this->assertSame(0, Order::count());
    }
}
