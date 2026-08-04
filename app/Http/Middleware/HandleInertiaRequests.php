<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Pipeline;
use App\Models\User;
use App\Support\OkrNotifications;
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
                    'crm' => $user->canSee('crm'),
                    'kanban' => $user->canSee('kanban'),
                        'order' => $user->canSee('order'),
                        'mindmap' => $user->canSee('mindmap'),
                        'script' => $user->canSee('script'),
                        'content' => $user->canSee('content'),     // kalender produksi konten
                        'tracking' => $user->canSee('tracking'),   // ringkasan progress owner/manager
                        'okr' => $user->canSee('okr'),          // OKR: objective & key result
                    'absensi' => $user->canSee('absensi'),      // absensi — semua peran
                    'payroll' => $user->canSee('payroll'),
                    'pembukuan' => $user->canSee('pembukuan'),
                        'user' => $user->canSee('user'),
                        'insight' => $user->canSee('insight'),      // Insight IG & YouTube
                        'upload' => $user->canSee('upload'),       // Upload konten multi-platform (template)
                        'prodpilot' => $user->canSee('prodpilot'),   // tautan eksternal, owner/it/manager
                        'akses' => $user->canSee('akses'),       // Manajemen Akses
                        'audit' => $user->canSee('audit'),       // Audit Log — owner + it
                    ],
                ] : null,
            ],

            // Flash message (redirect()->with('status', ...)) → toast di UI
            // Lazy closure: hanya dievaluasi saat dikirim
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],

            // Reminder kerja personal: hanya kartu Kanban aktif yang ditugaskan
            // ke user login, belum selesai, dan deadline-nya <= tiga hari lagi.
            // Dibagikan global agar lonceng/notifikasi tetap hidup di semua halaman.
            'workReminders' => fn () => $user
                ? Pipeline::query()
                    ->where('assigned_to', $user->id)
                    ->whereIn('category', Category::where('type', 'kanban')->select('key'))
                    ->whereNull('archived_at')
                    ->whereNull('completed_at')
                    ->where('done', false)
                    ->whereNotNull('deadline')
                    ->whereDate('deadline', '<=', today()->addDays(3))
                    ->orderBy('deadline')
                    ->limit(20)
                    ->get(['id', 'category', 'endorse', 'deadline'])
                    ->map(fn (Pipeline $card) => [
                        'id' => $card->id,
                        'title' => $card->endorse,
                        'deadline' => $card->deadline->toDateString(),
                        'days_left' => today()->diffInDays($card->deadline, false),
                        'url' => route('pipelines.kanban', [
                            'category' => $card->category,
                            'card' => $card->id,
                        ]),
                    ])
                : [],

            // Riwayat notifikasi dari database server. Berbeda dari
            // workReminders (hasil hitung deadline), item ini tetap ada sampai
            // dibaca dan muncul untuk semua role di Layout global.
            'serverNotifications' => fn () => $user ? $this->notifikasiServer($user) : [],
            'unreadNotificationsCount' => fn () => $user
                ? $user->unreadNotifications()->count()
                : 0,

            // True saat owner sedang "masuk sebagai" peran lain → tampilkan bilah "Kembali".
            'impersonating' => $request->session()->has('impersonator_id'),
        ];
    }

    /**
     * Riwayat notifikasi database untuk lonceng di Layout global.
     *
     *  Pengingat deadline kartu OKR dibuat MALAS tepat sebelum membaca
     *  (maks. 1/kartu/hari, lihat OkrNotifications::ingatkanDeadline):
     *  shared hosting produksi tak menjalankan scheduler, jadi kunjungan
     *  halamanlah pemicunya — dan karena dipicu duluan, pengingat baru
     *  langsung terlihat pada respons yang sama.
     *
     *  @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function notifikasiServer(User $user)
    {
        OkrNotifications::ingatkanDeadline($user);

        return $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notifikasi',
                'message' => $notification->data['message'] ?? '',
                'url' => $notification->data['url'] ?? null,
                'priority' => $notification->data['priority'] ?? null,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]);
    }
}
