<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Halaman Manajemen Akses: matriks peran × menu, tersimpan di `role_menu_access`. */
class AksesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_hanya_owner_manager_it_yang_bisa_buka(): void
    {
        foreach (['owner', 'manager', 'it'] as $boleh) {
            $this->actingAs($this->user($boleh))->get('/akses')->assertOk();
        }

        foreach (['admin', 'staff'] as $tolak) {
            $this->actingAs($this->user($tolak))->get('/akses')->assertForbidden();
        }
    }

    /** Yang view-only pun tak boleh mengubah lewat request langsung — tombolnya
     *  disembunyikan di Vue, tapi itu bukan gerbang. */
    public function test_admin_dan_staff_tak_bisa_mengubah(): void
    {
        foreach (['admin', 'staff'] as $tolak) {
            $this->actingAs($this->user($tolak))
                ->put('/akses', ['akses' => ['staff' => ['kanban', 'order']]])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('role_menu_access', ['role' => 'staff', 'menu' => 'order']);
    }

    public function test_perubahan_tersimpan_dan_langsung_berlaku(): void
    {
        $this->actingAs($this->user('owner'))
            ->put('/akses', ['akses' => ['staff' => ['kanban', 'mindmap', 'order']]])
            ->assertRedirect(route('akses.index'));

        $this->assertDatabaseHas('role_menu_access', ['role' => 'staff', 'menu' => 'order']);

        // instance BARU: canSee di-cache per-instance, jadi harus dibaca ulang
        $this->assertTrue($this->user('staff')->canSee('order'), 'staff harus melihat order sesudah dicentang');
    }

    public function test_mencabut_akses_menutup_halamannya(): void
    {
        $this->actingAs($this->user('owner'))
            ->put('/akses', ['akses' => ['staff' => ['kanban']]])   // mindmap dicabut
            ->assertRedirect();

        $this->assertFalse($this->user('staff')->canSee('mindmap'));
        $this->actingAs($this->user('staff'))->get('/mindmaps')->assertForbidden();
    }

    /** PAGAR ANTI-KEKUNCI LAPIS 1 (controller): kiriman yang mencoba mengosongkan
     *  owner diabaikan, barisnya di DB tak tersentuh. */
    public function test_kiriman_yang_mengosongkan_owner_diabaikan(): void
    {
        $sebelum = DB::table('role_menu_access')->where('role', 'owner')->count();
        $this->assertGreaterThan(0, $sebelum, 'prasyarat: owner punya baris');

        $this->actingAs($this->user('owner'))
            ->put('/akses', ['akses' => ['owner' => []]])
            ->assertRedirect();

        $this->assertSame($sebelum, DB::table('role_menu_access')->where('role', 'owner')->count(),
            'baris owner tak boleh terhapus');
    }

    /** PAGAR ANTI-KEKUNCI LAPIS 2 (model): walau baris owner di DB dipangkas
     *  SEBAGIAN — diedit manual, dump lama, migrasi separuh jalan — owner tetap
     *  bisa masuk. Tanpa ini halaman pengaturannya bisa jadi tak terjangkau
     *  selamanya & satu-satunya jalan keluar adalah mengedit database langsung.
     *
     *  Barisnya disisakan SATU, sengaja, bukan dihapus semua: kalau dikosongkan
     *  total, `izinDariDb()` mengembalikan null & yang menyelamatkan adalah
     *  fallback ke MENU_ACCESS — pagar owner-nya sendiri tak pernah teruji
     *  (versi pertama tes ini hijau walau pagarnya dilumpuhkan). Dgn sisa satu
     *  baris, jalur DB-lah yang dipakai, jadi cuma pagar ini yang bisa menolong. */
    public function test_owner_tetap_masuk_walau_barisnya_dipangkas_di_db(): void
    {
        DB::table('role_menu_access')->where('role', 'owner')->where('menu', '!=', 'kanban')->delete();
        $this->assertSame(1, DB::table('role_menu_access')->where('role', 'owner')->count(),
            'prasyarat: owner harus punya SATU baris, bukan nol');

        $owner = $this->user('owner');
        foreach (array_keys(User::MENUS) as $menu) {
            $this->assertTrue($owner->canSee($menu), "owner harus tetap bisa lihat {$menu}");
        }
        $this->actingAs($owner)->get('/akses')->assertOk();
    }

    public function test_menu_ngawur_ditolak(): void
    {
        $this->actingAs($this->user('owner'))
            ->put('/akses', ['akses' => ['staff' => ['kanban', 'menu_palsu']]])
            ->assertSessionHasErrors('akses.staff.1');

        $this->assertDatabaseMissing('role_menu_access', ['menu' => 'menu_palsu']);
    }

    /** Peran di luar ROLES dibuang, bukan disimpan sbg baris yatim. */
    public function test_peran_tak_dikenal_diabaikan(): void
    {
        $this->actingAs($this->user('owner'))
            ->put('/akses', ['akses' => ['hantu' => ['kanban'], 'staff' => ['kanban']]])
            ->assertRedirect();

        $this->assertDatabaseMissing('role_menu_access', ['role' => 'hantu']);
    }

    /** Isi awal migrasi = aturan lama persis, jangan sampai ada yang kehilangan
     *  atau kebagian akses gara-gara pindah ke DB. */
    public function test_isi_awal_sama_dengan_aturan_lama(): void
    {
        $this->assertTrue($this->user('manager')->canSee('pembukuan'));
        $this->assertFalse($this->user('manager')->canSee('user'));
        $this->assertTrue($this->user('admin')->canSee('kanban'));
        $this->assertFalse($this->user('admin')->canSee('order'));
        $this->assertTrue($this->user('staff')->canSee('kanban'));
        $this->assertFalse($this->user('staff')->canSee('pembukuan'));
    }

    /** Tabel kosong (mis. migrasi belum jalan di server) tak boleh membuka semua
     *  pintu — harus jatuh ke aturan di kode, bukan mengizinkan apa pun. */
    public function test_tabel_kosong_jatuh_ke_aturan_kode(): void
    {
        DB::table('role_menu_access')->delete();

        $this->assertTrue($this->user('staff')->canSee('kanban'));
        $this->assertFalse($this->user('staff')->canSee('pembukuan'));
        $this->assertTrue($this->user('owner')->canSee('user'));
    }
}
