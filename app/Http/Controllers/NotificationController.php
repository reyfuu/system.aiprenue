<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Aksi baca notifikasi personal.
 *
 * Route-nya hanya memakai middleware auth, bukan izin menu: staff harus dapat
 * membaca notifikasinya meski sumbernya adalah OKR yang tidak boleh ia buka.
 */
class NotificationController extends Controller
{
    public function markRead(Request $request, DatabaseNotification $notification)
    {
        // Route model binding dapat menemukan notifikasi user lain, jadi
        // kepemilikan wajib ditegakkan di server dan bukan cuma lewat UI.
        abort_unless(
            $notification->notifiable_type === $request->user()::class
                && (int) $notification->notifiable_id === (int) $request->user()->id,
            403,
            'Notifikasi ini bukan milik Anda.'
        );

        $notification->markAsRead();

        return back();
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Semua notifikasi sudah dibaca.');
    }
}
