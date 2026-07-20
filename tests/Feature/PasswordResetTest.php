<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Lupa password: user memasang passwordnya sendiri lewat tautan di email. */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_lupa_password_kebuka(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_tautan_reset_terkirim_ke_email_terdaftar(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'teman@example.com']);

        $this->post('/forgot-password', ['email' => 'teman@example.com'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    /** Email tak terdaftar dijawab SAMA PERSIS dgn yang terdaftar — kalau beda,
     *  halaman ini jadi alat menebak siapa saja yang punya akun di sini. */
    public function test_email_tak_terdaftar_dijawab_sama_dan_tak_mengirim_apa_pun(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'bukan-siapa-siapa@example.com'])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_password_baru_tersimpan_dan_bisa_dipakai_masuk(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'teman@example.com']);

        $this->post('/forgot-password', ['email' => 'teman@example.com']);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => 'teman@example.com',
            'password'              => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('passwordbaru123', $user->fresh()->password));

        // Benar-benar bisa dipakai masuk, bukan cuma tersimpan di DB.
        $this->post('/login', ['email' => 'teman@example.com', 'password' => 'passwordbaru123'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_token_ngawur_ditolak(): void
    {
        $user = User::factory()->create(['email' => 'teman@example.com', 'password' => Hash::make('lamaaa123')]);

        $this->post('/reset-password', [
            'token'                 => 'token-karangan',
            'email'                 => 'teman@example.com',
            'password'              => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('lamaaa123', $user->fresh()->password), 'password lama tak boleh berubah');
    }

    /** Salah ketik password baru = terkunci di luar & harus minta tautan lagi,
     *  jadi konfirmasi wajib cocok. */
    public function test_konfirmasi_password_wajib_cocok(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'teman@example.com', 'password' => Hash::make('lamaaa123')]);
        $this->post('/forgot-password', ['email' => 'teman@example.com']);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => 'teman@example.com',
            'password'              => 'passwordbaru123',
            'password_confirmation' => 'beda-sendiri',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('lamaaa123', $user->fresh()->password));
    }

    public function test_password_minimal_8_karakter(): void
    {
        $this->post('/reset-password', [
            'token'                 => 'apa-saja',
            'email'                 => 'teman@example.com',
            'password'              => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors('password');
    }

    /** Token cuma boleh sekali pakai — kalau bisa diulang, tautan lama yang
     *  bocor (mis. dari email yang diteruskan) tetap bisa membajak akun. */
    public function test_token_tak_bisa_dipakai_dua_kali(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'teman@example.com']);
        $this->post('/forgot-password', ['email' => 'teman@example.com']);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $payload = [
            'token'                 => $token,
            'email'                 => 'teman@example.com',
            'password'              => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ];

        $this->post('/reset-password', $payload)->assertRedirect(route('login'));

        // pakai ulang token yang sama
        $this->post('/reset-password', array_merge($payload, [
            'password'              => 'dibajak12345',
            'password_confirmation' => 'dibajak12345',
        ]))->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('passwordbaru123', $user->fresh()->password));
    }

    public function test_halaman_reset_kebuka_dari_tautan(): void
    {
        $this->get('/reset-password/token-contoh?email=teman@example.com')->assertOk();
    }
}
