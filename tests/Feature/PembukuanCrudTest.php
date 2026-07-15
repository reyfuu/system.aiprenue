<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CRUD transaksi & inventaris (modul Pembukuan).
 *  Menu `pembukuan` cuma dimiliki owner/it/manager — staff ditolak di gerbang menu,
 *  bukan gerbang canManage. */
class PembukuanCrudTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function trx(array $o = []): array
    {
        return array_merge([
            'type' => 'pemasukan', 'category' => 'Endorse',
            'description' => 'Bayaran brand', 'amount_idr' => 5_000_000, 'date' => '2026-07-01',
        ], $o);
    }

    private function inv(array $o = []): array
    {
        return array_merge([
            'name' => 'Kamera', 'qty' => 2, 'unit_value_idr' => 15_000_000, 'month' => '2026-07-01',
        ], $o);
    }

    // ---- Transaksi ----

    public function test_crud_transaksi(): void
    {
        $owner = $this->user();

        $this->actingAs($owner)->post('/transactions', $this->trx())->assertSessionHasNoErrors();
        $t = Transaction::first();
        $this->assertSame('pemasukan', $t->type);
        $this->assertSame('5000000.00', $t->amount_idr);

        $this->actingAs($owner)->put('/transactions/'.$t->id, $this->trx(['amount_idr' => 7_000_000]))
            ->assertSessionHasNoErrors();
        $this->assertSame('7000000.00', $t->fresh()->amount_idr);

        $this->actingAs($owner)->delete('/transactions/'.$t->id)->assertSessionHasNoErrors();
        $this->assertSame(0, Transaction::count());
    }

    public function test_type_transaksi_dibatasi_daftar(): void
    {
        $this->actingAs($this->user())->post('/transactions', $this->trx(['type' => 'ngawur']))
            ->assertSessionHasErrors('type');

        $this->assertSame(0, Transaction::count());
    }

    public function test_nominal_transaksi_tak_boleh_negatif(): void
    {
        $this->actingAs($this->user())->post('/transactions', $this->trx(['amount_idr' => -1]))
            ->assertSessionHasErrors('amount_idr');
    }

    public function test_staff_tak_punya_akses_pembukuan(): void
    {
        $this->actingAs($this->user('staff'))->post('/transactions', $this->trx())->assertForbidden();
        $this->assertSame(0, Transaction::count());
    }

    public function test_manager_boleh_kelola_transaksi(): void
    {
        $this->actingAs($this->user('manager'))->post('/transactions', $this->trx())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Transaction::count());
    }

    // ---- Inventaris ----

    public function test_crud_inventaris(): void
    {
        $owner = $this->user();

        $this->actingAs($owner)->post('/inventories', $this->inv())->assertSessionHasNoErrors();
        $i = Inventory::first();
        $this->assertSame('Kamera', $i->name);
        $this->assertSame(2, $i->qty);

        $this->actingAs($owner)->put('/inventories/'.$i->id, $this->inv(['qty' => 5]))
            ->assertSessionHasNoErrors();
        $this->assertSame(5, $i->fresh()->qty);

        $this->actingAs($owner)->delete('/inventories/'.$i->id)->assertSessionHasNoErrors();
        $this->assertSame(0, Inventory::count());
    }

    public function test_qty_inventaris_harus_bilangan_bulat_tak_negatif(): void
    {
        $this->actingAs($this->user())->post('/inventories', $this->inv(['qty' => -3]))
            ->assertSessionHasErrors('qty');

        $this->actingAs($this->user())->post('/inventories', $this->inv(['qty' => 1.5]))
            ->assertSessionHasErrors('qty');
    }

    public function test_staff_tak_boleh_kelola_inventaris(): void
    {
        $this->actingAs($this->user('staff'))->post('/inventories', $this->inv())->assertForbidden();
        $this->assertSame(0, Inventory::count());
    }
}
