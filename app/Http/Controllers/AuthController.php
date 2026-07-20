<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        // Sudah login → langsung ke halaman awal sesuai role
        if (Auth::check()) {
            return redirect()->route(Auth::user()->homeRoute());
        }

        // Tampilkan halaman login React (tanpa sidebar)
        return Inertia::render('Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route(Auth::user()->homeRoute()));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Email atau password salah.']);
    }

    public function showRegister()
    {
        // Sudah login → tak ada gunanya lihat form daftar
        if (Auth::check()) {
            return redirect()->route(Auth::user()->homeRoute());
        }

        return Inertia::render('Register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            // `confirmed` = wajib cocok dgn field password_confirmation.
            // min:6 disamakan dgn UserController biar tak ada dua standar panjang.
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Peran pendaftar dipatok 'staff' di server, BUKAN dari input.
        // Kalau ini pernah diambil dari request, siapa pun bisa mendaftar
        // sebagai owner. Jangan pernah pindahkan ke dalam validate() di atas.
        $data['role'] = 'staff';

        Auth::login(User::create($data));
        $request->session()->regenerate();

        return redirect()->route(Auth::user()->homeRoute())
            ->with('status', 'Akun dibuat. Selamat datang, '.$data['name'].'.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
