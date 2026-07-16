<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** CRUD user. Menu `user` cuma dimiliki owner & it — manager pun ditolak. */
class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'Staf Baru', 'email' => 'staf@example.com',
            'password' => 'rahasia123', 'role' => 'staff',
        ], $o);
    }

    public function test_buat_user_menyimpan_password_ter_hash(): void
    {
        $this->actingAs($this->user())->post('/users', $this->payload())->assertSessionHasNoErrors();

        $baru = User::where('email', 'staf@example.com')->first();
        $this->assertSame('staff', $baru->role);
        $this->assertNotSame('rahasia123', $baru->password, 'password tak boleh tersimpan polos');
        $this->assertTrue(Hash::check('rahasia123', $baru->password));
    }

    public function test_email_harus_unik(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->post('/users', $this->payload());

        $this->actingAs($owner)->post('/users', $this->payload(['name' => 'Lain']))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'staf@example.com')->count());
    }

    public function test_password_minimal_6_karakter(): void
    {
        $this->actingAs($this->user())->post('/users', $this->payload(['password' => '123']))
            ->assertSessionHasErrors('password');
    }

    public function test_role_dibatasi_daftar(): void
    {
        $this->actingAs($this->user())->post('/users', $this->payload(['role' => 'dewa']))
            ->assertSessionHasErrors('role');

        $this->assertSame(0, User::where('email', 'staf@example.com')->count());
    }

    public function test_edit_user_boleh_pakai_email_sendiri(): void
    {
        $owner = $this->user();
        $target = User::factory()->create(['email' => 'lama@example.com', 'role' => 'staff']);

        // Rule::unique()->ignore($user->id) — email sendiri tak boleh dianggap bentrok
        $this->actingAs($owner)->put('/users/'.$target->id, [
            'name' => 'Nama Baru', 'email' => 'lama@example.com', 'role' => 'staff', 'password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Nama Baru', $target->fresh()->name);
    }

    public function test_password_kosong_saat_edit_tak_mengubah_password(): void
    {
        $owner = $this->user();
        $target = User::factory()->create(['role' => 'staff']);
        $lama = $target->password;

        $this->actingAs($owner)->put('/users/'.$target->id, [
            'name' => $target->name, 'email' => $target->email, 'role' => 'staff', 'password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame($lama, $target->fresh()->password);
    }

    public function test_password_diisi_saat_edit_akan_mengganti_password(): void
    {
        $owner = $this->user();
        $target = User::factory()->create(['role' => 'staff']);

        $this->actingAs($owner)->put('/users/'.$target->id, [
            'name' => $target->name, 'email' => $target->email, 'role' => 'staff', 'password' => 'gantibaru',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('gantibaru', $target->fresh()->password));
    }

    public function test_tak_bisa_menghapus_akun_sendiri(): void
    {
        $owner = $this->user();

        $this->actingAs($owner)->delete('/users/'.$owner->id);

        $this->assertNotNull($owner->fresh(), 'akun sendiri tak boleh terhapus');
    }

    public function test_hapus_user_lain(): void
    {
        $target = User::factory()->create(['role' => 'staff']);

        $this->actingAs($this->user())->delete('/users/'.$target->id)->assertSessionHasNoErrors();

        $this->assertNull($target->fresh());
    }

    public function test_manager_dan_staff_tak_punya_akses_menu_user(): void
    {
        foreach (['manager', 'staff'] as $role) {
            $this->actingAs($this->user($role))->post('/users', $this->payload())->assertForbidden();
        }

        $this->assertSame(0, User::where('email', 'staf@example.com')->count());
    }

    /** Staff cuma boleh Kanban & Mindmap. Konstanta ini menyetir dua hal sekaligus:
     *  gerbang route (EnsureMenuAccess) & daftar menu sidebar (HandleInertiaRequests). */
    public function test_staff_cuma_boleh_kanban_dan_mindmap(): void
    {
        $staff = $this->user('staff');

        foreach (['kanban', 'mindmap'] as $boleh) {
            $this->assertTrue($staff->canSee($boleh), "staff harus boleh lihat {$boleh}");
        }

        foreach (['dashboard', 'pipeline', 'order', 'script', 'pembukuan', 'user'] as $tolak) {
            $this->assertFalse($staff->canSee($tolak), "staff tak boleh lihat {$tolak}");
        }
    }
}
