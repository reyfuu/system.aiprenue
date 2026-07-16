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
            'tipe_order'       => 'coaching_1on1',
            'account'          => 'fk',
            'nama_customer'    => 'Budi',
            'tipe_pembayaran'  => 'full',
            'tanggal_deadline' => '2026-08-01',
            'telepon'          => '08123456789',
            'kota'             => 'Kota Bandung',
            'total_idr'        => 1_000_000,
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

    public function test_field_penting_wajib_diisi(): void
    {
        foreach (['tanggal_deadline', 'telepon', 'kota', 'nama_customer'] as $field) {
            $payload = $this->payload();
            unset($payload[$field]);

            $this->actingAs($this->user())->post('/orders', $payload)
                ->assertSessionHasErrors($field);   // NB: arg ke-3 assertSessionHasErrors = error bag, bukan pesan
        }

        $this->assertSame(0, Order::count());
    }

    /** Nilai order tak boleh kosong — tapi boleh IDR saja, USD saja, atau dua-duanya.
     *  `required` biasa tak cukup: bagi Laravel, 0 itu "terisi". */
    public function test_nilai_order_wajib_minimal_satu_mata_uang(): void
    {
        $owner = $this->user();

        // dua-duanya kosong → ditolak
        $this->actingAs($owner)->post('/orders', $this->payload(['total_idr' => 0, 'total_usd' => 0]))
            ->assertSessionHasErrors('total_idr');
        $this->actingAs($owner)->post('/orders', $this->payload(['total_idr' => null, 'total_usd' => null]))
            ->assertSessionHasErrors('total_idr');
        $this->assertSame(0, Order::count());

        // USD saja → boleh (order luar negeri tanpa nominal rupiah)
        $this->actingAs($owner)->post('/orders', $this->payload(['total_idr' => 0, 'total_usd' => 500]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());

        // IDR saja → boleh
        $this->actingAs($owner)->post('/orders', $this->payload(['total_usd' => 0]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Order::count());
    }

    /** Wajib ditegakkan aplikasi, BUKAN NOT NULL di DB — baris order lama hasil
     *  impor .sql banyak yang kosong & akan ditolak seluruhnya. */
    public function test_kolom_wajib_tetap_nullable_di_database(): void
    {
        foreach (['tanggal_deadline', 'telepon', 'kota', 'email'] as $col) {
            $this->assertTrue(
                collect(\Illuminate\Support\Facades\Schema::getColumns('orders'))
                    ->firstWhere('name', $col)['nullable'],
                "kolom {$col} harus nullable di DB — baris lama boleh kosong"
            );
        }
    }

    /** Output order lewat pivot `order_output`. Pilihannya = tabel `outputs`, satu
     *  sumber dgn kartu Sales/Kanban — jangan ada daftar kedua. */
    public function test_output_order_tersimpan_lewat_pivot(): void
    {
        $reels = \App\Models\Output::firstWhere('name', 'Reels') ?? \App\Models\Output::create(['name' => 'Reels']);
        $foto = \App\Models\Output::firstWhere('name', 'Foto');
        $this->assertNotNull($foto, "output 'Foto' harus ada dari migrasi");

        $this->actingAs($this->user())
            ->post('/orders', $this->payload(['outputs' => [$reels->id, $foto->id]]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Foto', 'Reels'],
            Order::first()->outputs->pluck('name')->sort()->values()->all()
        );
    }

    /** `outputs` bukan kolom di tabel orders — kalau ikut terbawa ke Order::create(),
     *  Eloquent melempar. Order tanpa output pun harus tetap tersimpan. */
    public function test_order_tanpa_output_tetap_tersimpan(): void
    {
        $this->actingAs($this->user())->post('/orders', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Order::count());
        $this->assertCount(0, Order::first()->outputs);
    }

    public function test_output_ngawur_ditolak(): void
    {
        $this->actingAs($this->user())
            ->post('/orders', $this->payload(['outputs' => [999999]]))
            ->assertSessionHasErrors('outputs.0');

        $this->assertSame(0, Order::count());
    }

    /** Edit harus MENGGANTI daftar output, bukan menambahi. */
    public function test_edit_order_mengganti_daftar_output(): void
    {
        $a = \App\Models\Output::create(['name' => 'Output A']);
        $b = \App\Models\Output::create(['name' => 'Output B']);

        $this->actingAs($this->user())->post('/orders', $this->payload(['outputs' => [$a->id]]));
        $order = Order::first();
        $this->assertSame([$a->id], $order->outputs->pluck('id')->all());

        $this->actingAs($this->user())
            ->put('/orders/'.$order->id, $this->payload(['outputs' => [$b->id]]))
            ->assertSessionHasNoErrors();

        $this->assertSame([$b->id], $order->fresh()->outputs->pluck('id')->all());
    }

    /** Hapus order ikut membersihkan pivot — cascadeOnDelete. */
    public function test_hapus_order_ikut_membuang_baris_pivot(): void
    {
        $out = \App\Models\Output::create(['name' => 'Output X']);
        $this->actingAs($this->user())->post('/orders', $this->payload(['outputs' => [$out->id]]));
        $order = Order::first();

        $this->actingAs($this->user())->delete('/orders/'.$order->id)->assertSessionHasNoErrors();

        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('order_output')->where('order_id', $order->id)->count());
        $this->assertNotNull($out->fresh(), 'output-nya sendiri jangan ikut terhapus');
    }

    /** Filter kolom Output lewat pivot. */
    public function test_filter_output_menyaring_order(): void
    {
        $reels = \App\Models\Output::firstWhere('name', 'Reels') ?? \App\Models\Output::create(['name' => 'Reels']);
        $foto = \App\Models\Output::firstWhere('name', 'Foto');
        $owner = $this->user();

        $this->actingAs($owner)->post('/orders', $this->payload(['nama_customer' => 'Pakai Reels', 'outputs' => [$reels->id]]));
        $this->actingAs($owner)->post('/orders', $this->payload(['nama_customer' => 'Pakai Foto', 'outputs' => [$foto->id]]));
        $this->actingAs($owner)->post('/orders', $this->payload(['nama_customer' => 'Tanpa Output']));

        $this->actingAs($owner)->get('/orders?output='.$reels->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.output', (string) $reels->id)
                ->where('orders.data', fn ($rows) => collect($rows)->pluck('nama_customer')->all() === ['Pakai Reels'])
            );

        // tanpa filter → ketiganya
        $this->actingAs($owner)->get('/orders')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('orders.data', fn ($rows) => count($rows) === 3));
    }

    /** Order dgn banyak output tetap muncul SEKALI saat disaring.
     *  NB: tes ini juga lolos dgn join — selama filternya satu output, join memang
     *  belum menduplikat. Yang dijaga di sini cuma perilakunya; alasan memilih
     *  whereHas ada di komentar OrderController (tahan saat filter jadi multi-output). */
    public function test_order_dengan_banyak_output_muncul_sekali(): void
    {
        $reels = \App\Models\Output::firstWhere('name', 'Reels') ?? \App\Models\Output::create(['name' => 'Reels']);
        $foto = \App\Models\Output::firstWhere('name', 'Foto');
        $video = \App\Models\Output::firstWhere('name', 'Video');

        $this->actingAs($this->user())->post('/orders', $this->payload([
            'nama_customer' => 'Tiga Output', 'outputs' => [$reels->id, $foto->id, $video->id],
        ]));

        $this->actingAs($this->user())->get('/orders?output='.$reels->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('orders.data', fn ($rows) => count($rows) === 1));
    }

    /** Tabel butuh relasi outputs ikut terkirim, kalau tidak kolomnya kosong melulu. */
    public function test_output_ikut_terkirim_ke_tabel(): void
    {
        $foto = \App\Models\Output::firstWhere('name', 'Foto');
        $this->actingAs($this->user())->post('/orders', $this->payload(['outputs' => [$foto->id]]));

        $this->actingAs($this->user())->get('/orders')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.data.0.outputs', fn ($out) => collect($out)->pluck('name')->all() === ['Foto'])
                ->has('outputList')   // pilihan dropdown filter
            );
    }

    public function test_staff_tak_boleh_membuat_order(): void
    {
        $this->actingAs($this->user('staff'))->post('/orders', $this->payload())->assertForbidden();
        $this->assertSame(0, Order::count());
    }
}
