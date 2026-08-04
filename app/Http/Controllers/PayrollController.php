<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $periodRequest = $request->query('period');
        if (is_string($periodRequest) && preg_match('/^\d{4}-\d{2}$/', $periodRequest)) {
            try {
                $selectedPeriod = $periodRequest;
                Carbon::createFromFormat('Y-m', $selectedPeriod);
            } catch (\Throwable) {
                $selectedPeriod = null;
            }
        }

        if (empty($selectedPeriod)) {
            $selectedPeriod = now()->format('Y-m');
        }

        $periods = PayrollPeriod::query()
            ->orderByDesc('period')
            ->get(['id', 'period', 'status', 'start_date', 'end_date', 'notes', 'generated_by', 'created_at'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'period' => $p->period,
                'status' => $p->status,
                'start_date' => $p->start_date?->toDateString(),
                'end_date' => $p->end_date?->toDateString(),
                'notes' => $p->notes,
            ])
            ->values();

        $period = PayrollPeriod::with('entries.user')->where('period', $selectedPeriod)->first();

        $entries = $period?->entries->map(fn ($entry) => [
            'id' => $entry->id,
            'user' => $entry->user?->name,
            'user_id' => $entry->user_id,
            'work_days' => (int) $entry->work_days,
            'attendance_days' => (int) $entry->attendance_days,
            'absent_days' => (int) $entry->absent_days,
            'late_minutes' => (int) $entry->late_minutes,
            'overtime_minutes' => (int) $entry->overtime_minutes,
            'base_salary' => (float) $entry->base_salary,
            'allowance' => (float) $entry->allowance,
            'overtime_rate' => (float) $entry->overtime_rate,
            'overtime_amount' => (float) $entry->overtime_amount,
            'gross_salary' => (float) $entry->gross_salary,
            'deductions' => (float) $entry->deductions,
            'net_salary' => (float) $entry->net_salary,
            'notes' => $entry->notes,
        ])->values() ?? collect();

        $summary = $this->summaryFromEntries($entries);
        $preview = null;

        if ($period === null && $request->user()->canManage()) {
            [$previewEntries, $previewSummary] = $this->previewPeriodEntries($selectedPeriod);
            $preview = [
                'entries' => $previewEntries,
                'summary' => $previewSummary,
            ];
        }

        return Inertia::render('Payroll/Index', [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'active' => $period ? [
                'id' => $period->id,
                'status' => $period->status,
                'start_date' => $period->start_date?->toDateString(),
                'end_date' => $period->end_date?->toDateString(),
                'created_at' => $period->created_at?->toDateString(),
            ] : null,
            'canManage' => $request->user()->canManage(),
            'entries' => $entries,
            'summary' => $summary,
            'preview' => $preview,
        ]);
    }

    public function generate(Request $request)
    {
        abort_unless($request->user()->canManage(), 403, 'Tidak ada otorisasi untuk menghitung payroll.');

        $data = $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $period = $data['period'] ?? now()->format('Y-m');
        $periodStart = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $prepared = $this->calculateEntriesForPeriod($periodStart, $periodEnd);

        DB::transaction(function () use ($period, $periodStart, $periodEnd, $prepared, $request) {
            $periodModel = PayrollPeriod::updateOrCreate(
                ['period' => $period],
                [
                    'start_date' => $periodStart->toDateString(),
                    'end_date' => $periodEnd->toDateString(),
                    'status' => 'draft',
                    'notes' => null,
                    'generated_by' => $request->user()->id,
                ]
            );

            $periodModel->entries()->withTrashed()->forceDelete();

            foreach ($prepared['entries'] as $entry) {
                $periodModel->entries()->create([
                    'user_id' => $entry['user_id'],
                    'work_days' => $entry['work_days'],
                    'attendance_days' => $entry['attendance_days'],
                    'absent_days' => $entry['absent_days'],
                    'late_minutes' => $entry['late_minutes'],
                    'overtime_minutes' => $entry['overtime_minutes'],
                    'base_salary' => $entry['base_salary'],
                    'allowance' => $entry['allowance'],
                    'overtime_rate' => $entry['overtime_rate'],
                    'overtime_amount' => $entry['overtime_amount'],
                    'gross_salary' => $entry['gross_salary'],
                    'deductions' => $entry['deductions'],
                    'net_salary' => $entry['net_salary'],
                    'notes' => $entry['notes'],
                ]);
            }
        });

        return back()->with('status', "Payroll bulan $period berhasil digenerate.");
    }

    public function finalize(Request $request, PayrollPeriod $payrollPeriod)
    {
        abort_unless($request->user()->canManage(), 403, 'Tidak ada otorisasi untuk final payroll.');

        if ($payrollPeriod->status === 'final') {
            return back()->with('status', 'Payroll sudah difinalisasi.');
        }

        $payrollPeriod->update([
            'status' => 'final',
            'notes' => $payrollPeriod->notes ?? 'Difinalisasi pada '.now()->toDateTimeString(),
            'generated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Payroll difinalisasi.');
    }

    /** @return array{0: Collection<int, array>, 1: array<string, float>} */
    private function calculateEntriesForPeriod(Carbon $start, Carbon $end): array
    {
        $columns = ['id', 'name'];
        if (Schema::hasColumn('users', 'base_salary')) {
            $columns[] = 'base_salary';
        }
        if (Schema::hasColumn('users', 'meal_allowance')) {
            $columns[] = 'meal_allowance';
        }
        if (Schema::hasColumn('users', 'overtime_rate_per_hour')) {
            $columns[] = 'overtime_rate_per_hour';
        }
        if (Schema::hasColumn('users', 'late_penalty_per_minute')) {
            $columns[] = 'late_penalty_per_minute';
        }

        $users = User::orderBy('name')->get($columns);

        $workDays = $this->countWorkDays($start, $end);
        $attendances = Attendance::whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('user_id');

        $entries = $users->map(function (User $user) use ($attendances, $workDays, $start, $end) {
            $userAttendances = $attendances->get($user->id, collect());

            $attendanceDays = $userAttendances
                ->filter(fn (Attendance $attendance) => $attendance->isWorkday() && $attendance->check_in !== null)
                ->count();

            $lateMinutes = (int) $userAttendances->sum(fn (Attendance $attendance) => $attendance->lateMinutes());
            $overtimeMinutes = (int) $userAttendances->sum(fn (Attendance $attendance) => $attendance->overtimeMinutes());
            $absentDays = max(0, $workDays - $attendanceDays);

            $baseSalary = (float) ($user->base_salary ?? 0);
            $allowance = (float) ($user->meal_allowance ?? 0);
            $overtimeRate = (float) ($user->overtime_rate_per_hour ?? 0);
            $latePenalty = (float) ($user->late_penalty_per_minute ?? 0);

            $overtimeHours = round($overtimeMinutes / 60, 2);
            $overtimeAmount = $overtimeHours * $overtimeRate;
            $grossSalary = $baseSalary + $allowance + $overtimeAmount;
            $deductions = $lateMinutes * $latePenalty;
            $netSalary = max(0, $grossSalary - $deductions);

            $notes = [];
            if ($absentDays > 0) {
                $notes[] = "{$absentDays} hari tidak hadir";
            }
            if ($lateMinutes > 0) {
                $notes[] = "telat {$lateMinutes} menit";
            }
            if ($overtimeMinutes > 0) {
                $notes[] = "lembur {$overtimeMinutes} menit";
            }

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'work_days' => $workDays,
                'attendance_days' => $attendanceDays,
                'absent_days' => $absentDays,
                'late_minutes' => $lateMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'base_salary' => round($baseSalary, 2),
                'allowance' => round($allowance, 2),
                'overtime_rate' => round($overtimeRate, 2),
                'overtime_amount' => round($overtimeAmount, 2),
                'gross_salary' => round($grossSalary, 2),
                'deductions' => round($deductions, 2),
                'net_salary' => round($netSalary, 2),
                'notes' => implode(' · ', $notes),
            ];
        });

        return [
            'entries' => $entries,
            'summary' => $this->summaryFromEntries($entries),
        ];
    }

    private function summaryFromEntries(Collection|array $entries): array
    {
        if ($entries instanceof \Traversable) {
            $entries = collect($entries);
        } else {
            $entries = collect($entries);
        }

        return [
            'users' => $entries->count(),
            'gross_salary' => round($entries->sum('gross_salary'), 2),
            'net_salary' => round($entries->sum('net_salary'), 2),
            'deductions' => round($entries->sum('deductions'), 2),
            'overtime_hours' => round($entries->sum('overtime_minutes') / 60, 2),
            'late_minutes' => (int) $entries->sum('late_minutes'),
        ];
    }

    private function previewPeriodEntries(string $period): array
    {
        $periodStart = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $data = $this->calculateEntriesForPeriod($periodStart, $periodEnd);
        $userNames = User::query()
            ->whereIn('id', collect($data['entries'])->pluck('user_id')->all())
            ->pluck('name', 'id');

        return [
            collect($data['entries'])->map(fn (array $entry) => [
                ...$entry,
                'user' => $userNames[$entry['user_id']] ?? '',
            ])->values(),
            $data['summary'],
        ];
    }

    private function countWorkDays(Carbon $start, Carbon $end): int
    {
        $workDays = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ($cursor->isWeekday()) {
                $workDays++;
            }
            $cursor->addDay();
        }

        return $workDays;
    }
}
