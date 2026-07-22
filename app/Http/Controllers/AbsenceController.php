<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Absensi: semua peran boleh mengajukan cuti/sakit/izin & melihat riwayat sendiri.
 *  Tim manajemen (canManage) melihat semua pengajuan dan menyetujui/menolak. */
class AbsenceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $bisaKelola = $user->canManage();   // owner/manager/it/admin → lihat semua + approve

        $query = Absence::with('user:id,name')->latest('start_date')->latest('id');
        if (! $bisaKelola) {
            $query->where('user_id', $user->id);   // selain manajemen: hanya milik sendiri
        }

        return Inertia::render('Absensi', [
            'absences' => $query->get()->map(fn ($a) => [
                'id' => $a->id,
                'user' => $a->user?->name,
                'user_id' => $a->user_id,
                'type' => $a->type,
                'start_date' => $a->start_date?->toDateString(),
                'end_date' => $a->end_date?->toDateString(),
                'reason' => $a->reason,
                'attachment_url' => $a->attachment_path ? Storage::disk('public')->url($a->attachment_path) : null,
                'status' => $a->status,
            ]),
            'types' => Absence::TYPES,
            'statuses' => Absence::STATUSES,
            'canManage' => $bisaKelola,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Absence::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            // Keterangan (surat dokter dll) — opsional, terutama untuk sakit/izin.
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('absences', 'public');
        }

        $request->user()->absences()->create([
            'type' => $data['type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'reason' => $data['reason'] ?? null,
            'attachment_path' => $data['attachment_path'] ?? null,
            'status' => 'menunggu',
        ]);

        return back()->with('status', 'Pengajuan absensi terkirim.');
    }

    /** Setujui/tolak pengajuan — hanya tim manajemen. */
    public function updateStatus(Request $request, Absence $absence)
    {
        abort_unless($request->user()->canManage(), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Absence::STATUSES))],
        ]);

        $absence->update(['status' => $data['status']]);

        return back()->with('status', 'Status pengajuan diperbarui.');
    }

    public function destroy(Request $request, Absence $absence)
    {
        // Boleh hapus: pemilik pengajuan, atau tim manajemen.
        abort_unless($absence->user_id === $request->user()->id || $request->user()->canManage(), 403);

        if ($absence->attachment_path) {
            Storage::disk('public')->delete($absence->attachment_path);
        }
        $absence->delete();

        return back()->with('status', 'Pengajuan dihapus.');
    }
}
