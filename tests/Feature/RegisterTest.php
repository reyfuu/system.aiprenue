<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /** Data form yang valid; $ganti untuk menimpa satu field per kasus uji. */
    private function form(array $ganti = []): array
    {
        return array_merge([
            'name'                  => 'Budi',
            'email'                 => 'budi@contoh.com',
            'password'              => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $ganti);
    }

    public function test_pendaftar_baru_dibuat_dan_langsung_login(): void
    {
        $this->post('/register', $this->form())->assertRedirect();

        $user = User::where('email', 'budi@contoh.com')->first();
        $this->assertNotNull($user, 'User seharusnya dibuat.');
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_disimpan_dalam_bentuk_hash(): void
    {
        $this->post('/register', $this->form());

        $user = User::where('email', 'budi@contoh.com')->first();
        $this->assertNotSame('rahasia123', $user->password, 'Password tersimpan polos!');
        $this->assertTrue(password_verify('rahasia123', $user->password));
    }

    /** Pagar terpenting di fitur ini: peran dipatok server, bukan dari input. */
    public function test_role_dari_input_diabaikan_dan_dipatok_staff(): void
    {
        $this->post('/register', $this->form(['role' => 'owner']));

        $this->assertSame('staff', User::where('email', 'budi@contoh.com')->first()->role);
    }

    public function test_email_yang_sudah_dipakai_ditolak(): void
    {
        User::factory()->create(['email' => 'budi@contoh.com']);

        $this->post('/register', $this->form())
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, User::where('email', 'budi@contoh.com')->count());
    }

    public function test_konfirmasi_password_tak_cocok_ditolak(): void
    {
        $this->post('/register', $this->form(['password_confirmation' => 'beda123']))
            ->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'budi@contoh.com']);
    }

    public function test_user_yang_sudah_login_tak_melihat_form_daftar(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/register')
            ->assertRedirect();
    }
}
