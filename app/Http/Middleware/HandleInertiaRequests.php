<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    // Blade root yang memuat bundel Vue (@inertia ada di sini)
    protected $rootView = 'app';

    /**
     * Respons Inertia (bodinya JSON) TIDAK BOLEH disimpan browser.
     *
     * Gejalanya kalau tersimpan: user melihat JSON mentah memenuhi layar alih-alih
     * halaman. Terjadinya begini — tab lama memegang versi aset lama, aset di-build
     * ulang, permintaan berikutnya jadi tak cocok versi, dan browser menyajikan
     * kembali bodi JSON yang tersimpan untuk URL itu ke navigasi dokumen.
     *
     * `no-cache` bawaan session TIDAK cukup: artinya "revalidasi dulu", bukan
     * "jangan simpan". Yang dibutuhkan `no-store`.
     *
     * `Vary` juga dipasang di sini karena Inertia melewatkannya pada respons 409
     * (versi aset tak cocok) — persis jalur yang memicu masalah ini. Tanpa `Vary`,
     * browser tak membedakan varian Inertia dan varian dokumen untuk URL yang sama.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        if ($request->header('X-Inertia')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->headers->set('Vary', 'X-Inertia');
        }

        return $response;
    }

    // Versi aset — untuk cache-busting otomatis Inertia
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    // Props yang otomatis ada di SEMUA halaman (auth & flash)
    public function share(Request $request): array
    {
        $user = $request->user(); // user login (atau null)

        return [
            ...parent::share($request), // props bawaan Inertia (errors, dll)

            // Data auth yang dipakai Sidebar & guard UI
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,                 // id user
                    'name' => $user->name,               // nama tampil
                    'email' => $user->email,              // email
                    'role' => $user->role,               // role mentah
                    'canManage' => $user->canManage(),        // boleh CRUD?
                    // Peta menu yang boleh dilihat → dipakai Sidebar
                    'menus' => [
                        'dashboard' => $user->canSee('dashboard'),
                        'pipeline' => $user->canSee('pipeline'),
                        'kanban' => $user->canSee('kanban'),
                        'order' => $user->canSee('order'),
                        'mindmap' => $user->canSee('mindmap'),
                        'script' => $user->canSee('script'),
                        'content' => $user->canSee('content'),     // kalender produksi konten
                        'tracking' => $user->canSee('tracking'),   // ringkasan progress owner/manager
                        'absensi' => $user->canSee('absensi'),      // absensi — semua peran
                        'pembukuan' => $user->canSee('pembukuan'),
                        'user' => $user->canSee('user'),
                        'insight' => $user->canSee('insight'),      // Insight IG & YouTube
                        'upload' => $user->canSee('upload'),       // Upload konten multi-platform (template)
                        'prodpilot' => $user->canSee('prodpilot'),   // tautan eksternal, owner/it/manager
                        'akses' => $user->canSee('akses'),       // Manajemen Akses
                    ],
                ] : null,
            ],

            // Flash message (redirect()->with('status', ...)) → toast di UI
            // Lazy closure: hanya dievaluasi saat dikirim
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],

            // True saat owner sedang "masuk sebagai" peran lain → tampilkan bilah "Kembali".
            'impersonating' => $request->session()->has('impersonator_id'),
        ];
    }
}
