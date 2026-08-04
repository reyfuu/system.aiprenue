<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
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
        $month = (string) $request->query('month', now()->format('Y-m'));

        try {
            $awalBulan = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            $awalBulan = now()->startOfMonth();
            $month = $awalBulan->format('Y-m');
        }

        $akhirBulan = $awalBulan->copy()->endOfMonth();
        $opsiBulan = collect(range(0, 11))->map(fn($i) => $awal = now()->subMonths($i)->startOfMonth())->filter(fn($awal) => $awal->isAfter(now()->subMonths(24)));
        $bulanList = collect(range(0, 11))->map(fn($i) => now()->subMonths($i))->map(fn ($date) => [
            'value' => $date->format('Y-m'),
            'label' => $date->translatedFormat('F Y'),
        ]);

        $query = Absence::with('user:id,name')->latest('start_date')->latest('id');
        if (! $bisaKelola) {
            $query->where('user_id', $user->id);   // selain manajemen: hanya milik sendiri
        }

        $attendanceQuery = Attendance::with('user:id,name')
            ->whereBetween('work_date', [$awalBulan->toDateString(), $akhirBulan->toDateString()])
            ->orderByDesc('work_date')
            ->orderByDesc('id');

        if (! $bisaKelola) {
            $attendanceQuery->where('user_id', $user->id);
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
            'attendanceCanManage' => $bisaKelola,
            'attendances' => $attendanceQuery->get()->map(fn ($a) => $this->attendanceRow($a)),
            'attendanceMonth' => $month,
            'attendanceMonthOptions' => $bulanList->values(),
            'attendanceUsers' => $bisaKelola ? User::orderBy('name')->get(['id', 'name']) : [],
            'attendanceSummary' => [
                'totalRecords' => $attendanceQuery->count(),
                'todayHasRecord' => Attendance::where('user_id', $user->id)->where('work_date', now()->toDateString())->exists(),
            ],
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

    public function checkIn(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();
        $attendance = Attendance::firstOrNew([
            'user_id' => $user->id,
            'work_date' => $today,
        ]);

        if ($attendance->check_in) {
            return back()->with('status', 'Hari ini sudah check in.');
        }

        $attendance->fill([
            'check_in' => now(),
            'source' => 'self',
        ])->save();

        return back()->with('status', 'Check in berhasil.');
    }

    public function checkOut(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();
        $attendance = Attendance::where('user_id', $user->id)->where('work_date', $today)->first();

        if (! $attendance || ! $attendance->check_in) {
            return back()->with('status', 'Check in dulu sebelum check out.');
        }

        if ($attendance->check_out) {
            return back()->with('status', 'Hari ini sudah check out.');
        }

        $attendance->update([
            'check_out' => now(),
            'source' => 'self',
        ]);

        return back()->with('status', 'Check out berhasil.');
    }

    public function storeAttendance(Request $request)
    {
        abort_unless($request->user()->canManage(), 403, 'Hanya manajemen yang boleh input absensi manual.');

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'work_date' => ['required', 'date'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if (empty($data['check_in_time']) && empty($data['check_out_time'])) {
            return back()->withErrors([
                'check_in_time' => 'Isi jam masuk atau jam pulang.',
            ]);
        }

        $workDate = Carbon::parse($data['work_date'])->toDateString();
        $checkIn = $data['check_in_time'] ? Carbon::parse($workDate.' '.$data['check_in_time']) : null;
        $checkOut = $data['check_out_time'] ? Carbon::parse($workDate.' '.$data['check_out_time']) : null;

        if ($checkIn && $checkOut && $checkOut->lessThanOrEqualTo($checkIn)) {
            return back()->withErrors([
                'check_out_time' => 'Jam pulang harus setelah jam masuk.',
            ]);
        }

        Attendance::updateOrCreate(
            ['user_id' => $data['user_id'], 'work_date' => $workDate],
            [
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'note' => $data['note'] ?? null,
                'source' => 'manual',
            ]
        );

        return back()->with('status', 'Presensi manual disimpan.');
    }

    public function updateAttendance(Request $request, Attendance $attendance)
    {
        abort_unless($request->user()->canManage(), 403, 'Hanya manajemen yang boleh edit absensi manual.');

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'work_date' => ['required', 'date'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if (empty($data['check_in_time']) && empty($data['check_out_time'])) {
            return back()->withErrors([
                'check_in_time' => 'Isi jam masuk atau jam pulang.',
            ]);
        }

        $workDate = Carbon::parse($data['work_date'])->toDateString();
        $checkIn = $data['check_in_time'] ? Carbon::parse($workDate.' '.$data['check_in_time']) : null;
        $checkOut = $data['check_out_time'] ? Carbon::parse($workDate.' '.$data['check_out_time']) : null;

        if ($checkIn && $checkOut && $checkOut->lessThanOrEqualTo($checkIn)) {
            return back()->withErrors([
                'check_out_time' => 'Jam pulang harus setelah jam masuk.',
            ]);
        }

        $attendance->update([
            'user_id' => $data['user_id'],
            'work_date' => $workDate,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'note' => $data['note'] ?? null,
            'source' => 'manual',
        ]);

        return back()->with('status', 'Presensi manual diperbarui.');
    }

    public function destroyAttendance(Request $request, Attendance $attendance)
    {
        abort_unless($request->user()->canManage(), 403, 'Hanya manajemen yang boleh hapus absensi manual.');

        $attendance->delete();

        return back()->with('status', 'Presensi manual dihapus.');
    }

    /** Setujui/tolak pengajuan — hanya tim manajemen. */
    public function updateStatus(Request $request, Absence $absence)
    {
        abort_unless($request->user()->canManage(), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Absence::STATUSES))],
        ]);

        $absence->update(['status' => $data['status']]);

        \App\Models\AuditLog::record($data['status'] === 'approved' ? 'approve' : 'reject', $absence,
            ['status' => $absence->getOriginal('status')],
            ['status' => $data['status']]
        );

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

    private function attendanceRow(Attendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'user_id' => $attendance->user_id,
            'user' => $attendance->user?->name,
            'date' => $attendance->work_date?->toDateString(),
            'check_in' => $attendance->check_in?->format('H:i'),
            'check_out' => $attendance->check_out?->format('H:i'),
            'source' => $attendance->source,
            'note' => $attendance->note,
            'late_minutes' => $attendance->lateMinutes(),
            'overtime_minutes' => $attendance->overtimeMinutes(),
            'worked_minutes' => $attendance->workingMinutes(),
            'is_weekend' => (bool) $attendance->isWorkday() === false,
        ];
    }
}
