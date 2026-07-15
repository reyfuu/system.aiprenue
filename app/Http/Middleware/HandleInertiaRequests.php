<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    // Blade root yang memuat bundel React (@inertia ada di sini)
    protected $rootView = 'app';

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
                    'id'        => $user->id,                 // id user
                    'name'      => $user->name,               // nama tampil
                    'email'     => $user->email,              // email
                    'role'      => $user->role,               // role mentah
                    'canManage' => $user->canManage(),        // boleh CRUD?
                    // Peta menu yang boleh dilihat → dipakai Sidebar
                    'menus'     => [
                        'dashboard' => $user->canSee('dashboard'),
                        'pipeline'  => $user->canSee('pipeline'),
                        'kanban'    => $user->canSee('kanban'),
                        'order'     => $user->canSee('order'),
                        'mindmap'   => $user->canSee('mindmap'),
                        'script'    => $user->canSee('script'),
                        'pembukuan' => $user->canSee('pembukuan'),
                        'user'      => $user->canSee('user'),
                    ],
                ] : null,
            ],

            // Flash message (redirect()->with('status', ...)) → toast di UI
            // Lazy closure: hanya dievaluasi saat dikirim
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
