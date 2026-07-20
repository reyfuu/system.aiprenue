<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;

/** Lupa password: kirim tautan ke email, lalu user memasang passwordnya sendiri.
 *
 *  Memakai password broker bawaan Laravel (tabel `password_reset_tokens`):
 *  token diacak & DISIMPAN TER-HASH, kedaluwarsa otomatis, dan ada jeda antar
 *  permintaan. Menggulung sendiri berarti menulis ulang semua itu — dan salah
 *  satu saja luput berarti lubang keamanan.
 */
class PasswordResetController extends Controller
{
    /** Form "lupa password" (isi email). */
    public function showRequest()
    {
        return Inertia::render('ForgotPassword');
    }

    /** Kirim tautan reset ke email.
     *
     *  Jawabannya SELALU sama, berhasil atau tidak — sengaja. Kalau email yang
     *  tak terdaftar dijawab beda ("email tidak ditemukan"), halaman ini berubah
     *  jadi alat pengecek: siapa pun bisa menebak-nebak email mana yang punya
     *  akun di sini. Yang gagal tetap dicatat di log, bukan diperlihatkan.
     */
    public function sendLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            report(new \RuntimeException("Reset link gagal untuk {$request->input('email')}: {$status}"));
        }

        return back()->with('status', 'Kalau emailnya terdaftar, tautan untuk mengatur password sudah kami kirim. Cek inbox dan folder spam.');
    }

    /** Form pasang password baru (dibuka dari tautan di email). */
    public function showReset(Request $request, string $token)
    {
        return Inertia::render('ResetPassword', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /** Simpan password baru. */
    public function reset(Request $request)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            // Aturan bawaan Laravel: minimal 8 karakter. `confirmed` mewajibkan
            // field `password_confirmation` cocok — salah ketik password baru
            // tanpa ini = terkunci di luar & harus minta tautan lagi.
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    // Ganti remember_token: sesi "ingat saya" yang lama harus mati
                    // begitu password diganti — kalau tidak, orang yang sudah
                    // terlanjur masuk tetap bertahan padahal passwordnya diubah.
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Di sini pesannya BOLEH spesifik: pemakainya memang pemegang tautan,
            // dan "token kedaluwarsa" vs "email salah" itu beda tindakan.
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'Password berhasil dibuat. Silakan masuk.');
    }
}
