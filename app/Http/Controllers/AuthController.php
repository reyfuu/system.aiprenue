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

    /** Ganti password sendiri (self-service, tanpa email/SMTP).
     *
     *  Verifikasi lewat password lama, bukan tautan email. Rule `current_password`
     *  bawaan Laravel mencocokkan input ke password user yang sedang login —
     *  jadi orang lain yang menumpang sesi terbuka tetap tak bisa menggantinya
     *  tanpa tahu password lama. min:6 + confirmed disamakan dgn register().
     */
    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        // Cast `hashed` di model meng-hash otomatis saat disimpan.
        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Password berhasil diganti.');
    }

    /** Masuk sebagai peran lain untuk mengintip fiturnya (quick trick, bukan login manual).
     *
     *  HANYA owner asli yang boleh memulai — impersonasi peran yang lebih rendah
     *  itu turun hak, bukan naik. Id owner disimpan di sesi supaya bisa kembali.
     *  Peran login yang dipakai TETAP milik user sasaran (canSee/canManage baca
     *  role user), jadi tak ada logika otorisasi yang perlu diubah.
     */
    public function impersonate(Request $request, string $role)
    {
        // Gerbang: cuma owner asli & belum sedang menyamar (cegah rantai menyamar).
        if ($request->user()->role !== 'owner' || $request->session()->has('impersonator_id')) {
            abort(403);
        }

        // Ambil satu contoh user peran itu (bukan diri sendiri, bukan owner).
        $target = User::where('role', $role)
            ->where('id', '!=', $request->user()->id)
            ->first();

        if (! $target || $role === 'owner') {
            return back()->with('status', "Tak ada contoh user untuk peran ini.");
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::login($target);

        return redirect()->route($target->homeRoute())
            ->with('status', "Kamu sekarang masuk sebagai {$target->name} ({$role}).");
    }

    /** Kembali ke akun owner asli. Aman dipanggil siapa pun: tanpa penanda sesi
     *  `impersonator_id` (hanya dipasang aksi owner di atas) ini cuma redirect. */
    public function stopImpersonate(Request $request)
    {
        $id = $request->session()->pull('impersonator_id');
        $owner = $id ? User::find($id) : null;

        if ($owner) {
            Auth::login($owner);
        }

        // Balik ke Manajemen Akses (tempat impersonasi dimulai), bukan dashboard.
        // Owner selalu boleh lihat 'akses'; kalau entah bagaimana bukan owner,
        // jatuh ke homeRoute yang pasti boleh.
        $tujuan = $owner && $owner->canSee('akses') ? 'akses.index' : ($owner?->homeRoute() ?? 'login');

        return redirect()->route($tujuan)->with('status', 'Kembali ke akun sendiri.');
    }
}
