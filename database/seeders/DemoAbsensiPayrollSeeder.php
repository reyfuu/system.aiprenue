<?php

namespace Database\Seeders;

use App\Models\Absence;
use App\Models\Attendance;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DemoAbsensiPayrollSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::orderBy('id')->get();

        if ($users->isEmpty()) {
            return;
        }

        $this->seedUserPayrollFields($users);

        if (Schema::hasTable('attendances')) {
            $this->seedAttendance($users);
        }

        if (Schema::hasTable('absences')) {
            $this->seedAbsence($users);
        }

        if (Schema::hasTable('payroll_periods') && Schema::hasTable('payroll_entries') && Schema::hasTable('attendances')) {
            $this->seedPayrollPeriods($users);
        }
    }

    private function seedUserPayrollFields(Collection $users): void
    {
        if (! Schema::hasColumn('users', 'base_salary')) {
            return;
        }

        $defaults = [
            ['base_salary' => 6500000, 'meal_allowance' => 500000, 'overtime_rate_per_hour' => 25000, 'late_penalty_per_minute' => 1000],
            ['base_salary' => 4500000, 'meal_allowance' => 300000, 'overtime_rate_per_hour' => 20000, 'late_penalty_per_minute' => 800],
            ['base_salary' => 5500000, 'meal_allowance' => 350000, 'overtime_rate_per_hour' => 22000, 'late_penalty_per_minute' => 900],
            ['base_salary' => 7000000, 'meal_allowance' => 600000, 'overtime_rate_per_hour' => 28000, 'late_penalty_per_minute' => 1200],
        ];

        $users->values()->each(function (User $user, int $i) use ($defaults) {
            $cfg = $defaults[$i % count($defaults)];

            $user->update($cfg);
        });
    }

    private function seedAttendance(Collection $users): void
    {
        $periodStart = Carbon::now()->subMonth()->startOfMonth();
        $periodEnd = Carbon::now()->subMonth()->endOfMonth();

        foreach ($users as $idx => $user) {
            $cursor = $periodStart->copy();
            while ($cursor->lte($periodEnd)) {
                $workDate = $cursor->toDateString();
                if (! $cursor->isWeekday()) {
                    $cursor->addDay();
                    continue;
                }

                if (($idx + (int) $cursor->dayOfWeekIso) % 7 === 0) {
                    $cursor->addDay();
                    continue;
                }

                $hasAttendance = Attendance::where('user_id', $user->id)->whereDate('work_date', $workDate)->exists();
                if ($hasAttendance) {
                    $cursor->addDay();
                    continue;
                }

                $baseInMinute = 7 * 60 + 55;
                if (($idx + (int) $cursor->day) % 5 === 0) {
                    $checkInMinute = $baseInMinute + 15; // telat
                } else {
                    $checkInMinute = $baseInMinute + (($idx + (int) $cursor->day) % 8);
                }

                $baseOutMinute = 18 * 60;
                if (($idx + (int) $cursor->day) % 4 === 0) {
                    $checkOutMinute = $baseOutMinute + 45;
                } else {
                    $checkOutMinute = $baseOutMinute + 10;
                }

                $checkIn = $cursor->copy()->setTime(intdiv($checkInMinute, 60), $checkInMinute % 60);
                $checkOut = $cursor->copy()->setTime(intdiv($checkOutMinute, 60), $checkOutMinute % 60);

                Attendance::create([
                    'user_id' => $user->id,
                    'work_date' => $workDate,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'source' => 'manual',
                    'note' => 'Data demo seeder',
                ]);

                $cursor->addDay();
            }
        }
    }

    private function seedAbsence(Collection $users): void
    {
        $alreadyHas = Absence::count();
        if ($alreadyHas > 0) {
            return;
        }

        $today = Carbon::now()->toDateString();

        $samples = [
            ['type' => 'izin', 'status' => 'disetujui', 'startOffset' => -14, 'len' => 1],
            ['type' => 'sakit', 'status' => 'menunggu', 'startOffset' => -7, 'len' => 2],
            ['type' => 'cuti', 'status' => 'disetujui', 'startOffset' => -3, 'len' => 1],
        ];

        foreach ($samples as $i => $sample) {
            $user = $users[$i % $users->count()];
            $start = Carbon::parse($today)->addDays($sample['startOffset']);
            $end = $start->copy()->addDays($sample['len'] - 1);

            Absence::create([
                'user_id' => $user->id,
                'type' => $sample['type'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'reason' => 'Data demo: '.ucfirst($sample['type']).' untuk uji fitur',
                'status' => $sample['status'],
            ]);
        }
    }

    private function seedPayrollPeriods(Collection $users): void
    {
        $period = Carbon::now()->subMonth()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $summary = $this->calculateEntriesForPeriod($users, $start, $end);

        $periodModel = PayrollPeriod::updateOrCreate(
            ['period' => $period],
            [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => 'draft',
                'notes' => 'Payroll demo seeder',
                'generated_by' => $users->first()?->id,
            ],
        );

        PayrollEntry::withTrashed()->where('payroll_period_id', $periodModel->id)->forceDelete();
        foreach ($summary as $entry) {
            $periodModel->entries()->create($entry);
        }
    }

    private function calculateEntriesForPeriod(Collection $users, Carbon $start, Carbon $end): array
    {
        $workDays = $this->countWorkDays($start, $end);

        return $users->map(function (User $user) use ($start, $end, $workDays) {
            $attendances = Attendance::where('user_id', $user->id)
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->get();

            $attendanceDays = $attendances->filter(fn (Attendance $att) => $att->isWorkday() && $att->check_in !== null)->count();
            $lateMinutes = (int) $attendances->sum(fn (Attendance $att) => $att->lateMinutes());
            $overtimeMinutes = (int) $attendances->sum(fn (Attendance $att) => $att->overtimeMinutes());
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
        })->all();
    }

    private function countWorkDays(Carbon $start, Carbon $end): int
    {
        $count = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ($cursor->isWeekday()) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }
}
