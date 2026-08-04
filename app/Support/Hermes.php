<?php

namespace App\Support;

use App\Models\Absence;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\Pipeline;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\Transaction;
use App\Notifications\HermesDailyReportNotification;
use App\Support\ExchangeRate;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Hermes: ringkasan operasional harian otomatis.
 *
 * Dipakai dua cara:
 * 1) command artisan `hermes:daily-report` (cron/manual)
 * 2) pemicu lazy di middleware (shared hosting tanpa scheduler persisten).
 */
class Hermes
{
    public static function buildDailySummary(CarbonInterface $date): array
    {
        $dateStr = $date->toDateString();
        $rate = ExchangeRate::usdToIdr();

        $ordersCreated = 0;
        $ordersPaid = 0;
        $ordersValueCreated = 0.0;
        $ordersValuePaid = 0.0;
        $ordersDp = 0;
        $ordersFull = 0;

        if (Schema::hasTable('orders')) {
            $created = Order::query()
                ->whereDate('created_at', $dateStr)
                ->get(['total_idr', 'total_usd', 'tipe_pembayaran']);
            $ordersCreated = $created->count();
            $ordersValueCreated = (float) $created->sum('total_idr') + (float) $created->sum('total_usd') * $rate;
            $ordersDp = (int) $created->where('tipe_pembayaran', 'dp')->count();
            $ordersFull = (int) $created->where('tipe_pembayaran', 'full')->count();

            $paid = Order::query()
                ->whereDate('tanggal_bayar', $dateStr)
                ->get(['total_idr', 'total_usd', 'tipe_pembayaran']);
            $ordersPaid = $paid->count();
            $ordersValuePaid = (float) $paid->sum('total_idr') + (float) $paid->sum('total_usd') * $rate;
        }

        $absensiHariIni = 0;
        $izinMenunggu = 0;
        if (Schema::hasTable('attendances')) {
            $absensiHariIni = Attendance::query()->whereDate('work_date', $dateStr)->count();
        }
        if (Schema::hasTable('absences')) {
            $izinMenunggu = Absence::query()
                ->whereDate('start_date', '<=', $dateStr)
                ->whereDate('end_date', '>=', $dateStr)
                ->where('status', 'menunggu')
                ->count();
        }

        $crmNew = 0;
        $crmWonToday = 0;
        $crmDueSoon = 0;
        if (Schema::hasTable('pipelines')) {
            $salesBoards = Pipeline::categories('pipeline');
            $pipelineBase = Pipeline::query()
                ->whereIn('category', array_keys($salesBoards))
                ->whereNull('archived_at');

            $crmNew = (int) (clone $pipelineBase)
                ->whereDate('created_at', $dateStr)->count();
            $crmWonToday = (int) (clone $pipelineBase)
                ->whereDate('completed_at', $dateStr)
                ->where('done', true)
                ->count();
            $crmDueSoon = (int) (clone $pipelineBase)
                ->where('done', false)
                ->whereNotNull('deadline')
                ->whereBetween('deadline', [$date->startOfDay(), $date->copy()->addDays(7)->endOfDay()])
                ->count();
        }

        $payrollEntriesToday = 0;
        $payrollNetToday = 0.0;
        $payrollPeriodsUpdatedToday = 0;
        if (Schema::hasTable('payroll_periods') && Schema::hasTable('payroll_entries')) {
            $payrollEntriesToday = (int) PayrollEntry::query()
                ->whereDate('created_at', $dateStr)
                ->count();
            $payrollNetToday = (float) PayrollEntry::query()
                ->whereDate('created_at', $dateStr)
                ->sum('net_salary');
            $payrollPeriodsUpdatedToday = (int) PayrollPeriod::query()
                ->whereDate('updated_at', $dateStr)
                ->count();
        }

        $transaksiIn = 0;
        $transaksiOut = 0;
        if (Schema::hasTable('transactions')) {
            $transaksiIn = (float) Transaction::query()
                ->where('type', 'pemasukan')
                ->whereDate('date', $dateStr)
                ->sum('amount_idr');
            $transaksiOut = (float) Transaction::query()
                ->where('type', 'pengeluaran')
                ->whereDate('date', $dateStr)
                ->sum('amount_idr');
        }

        return [
            'date' => $dateStr,
            'orders' => [
                'created' => $ordersCreated,
                'created_value_idr' => $ordersValueCreated,
                'paid' => $ordersPaid,
                'paid_value_idr' => $ordersValuePaid,
                'dp' => $ordersDp,
                'full' => $ordersFull,
            ],
            'absensi' => [
                'hadir' => $absensiHariIni,
                'izin_dengan_status_menunggu' => $izinMenunggu,
            ],
            'crm' => [
                'new' => $crmNew,
                'won_today' => $crmWonToday,
                'due_soon' => $crmDueSoon,
            ],
            'payroll' => [
                'entries' => $payrollEntriesToday,
                'net' => $payrollNetToday,
                'periods_updated' => $payrollPeriodsUpdatedToday,
            ],
            'pembukuan' => [
                'pemasukan' => (float) $transaksiIn,
                'pengeluaran' => (float) $transaksiOut,
            ],
        ];
    }

    public static function formatMessage(array $s, string $date): string
    {
        $toRupiah = fn (float $nominal) => 'Rp '.number_format($nominal, 0, ',', '.');

        return "Laporan harian {$date}"
            .PHP_EOL.'- Order: '.$s['orders']['created'].' new ('.$toRupiah((float) $s['orders']['created_value_idr']).')'
            .PHP_EOL.'- Bayar hari ini: '.$s['orders']['paid'].' transaksi ('.$toRupiah((float) $s['orders']['paid_value_idr']).')'
            .PHP_EOL.'- Absensi: '.$s['absensi']['hadir'].' hadir · '.$s['absensi']['izin_dengan_status_menunggu'].' pengajuan menunggu'
            .PHP_EOL.'- CRM: '.$s['crm']['new'].' lead baru, '.$s['crm']['won_today'].' won, '.$s['crm']['due_soon'].' due ≤7 hari'
            .PHP_EOL.'- Pembukuan: in '.$toRupiah((float) $s['pembukuan']['pemasukan']).' · out '.$toRupiah((float) $s['pembukuan']['pengeluaran']);
    }

    public static function recipientsByRole(): Collection
    {
        $roles = trim((string) env('HERMES_REPORT_ROLES', 'owner,it'));
        $roles = array_filter(array_map('trim', explode(',', $roles)), static fn ($r) => $r !== '');
        if (empty($roles)) {
            $roles = ['owner', 'it'];
        }

        return User::query()->whereIn('role', $roles)->get();
    }

    public static function autoReport(?User $user = null, ?CarbonInterface $date = null): void
    {
        if (! $user || ! self::shouldReceiveReport($user)) {
            return;
        }

        $date = $date ? $date->copy() : today();
        $dateStr = $date->toDateString();
        $already = $user->notifications()
            ->whereDate('created_at', $dateStr)
            ->latest()
            ->limit(20)
            ->get(['data'])
            ->contains(fn ($n) => ($n->data['kind'] ?? null) === 'hermes_daily_report' && ($n->data['report_date'] ?? null) === $dateStr);

        if ($already) {
            return;
        }

        $summary = self::buildDailySummary($date);
        $title = "Hermes Daily Report — {$date->translatedFormat('d F Y')}";
        $user->notify(new HermesDailyReportNotification(
            title: $title,
            message: self::formatMessage($summary, $date->translatedFormat('d M Y')),
            summary: $summary,
            reportDate: $dateStr,
        ));
    }

    public static function sendToConfiguredRoles(?CarbonInterface $date = null): void
    {
        $date = $date ? $date->copy() : today();
        $summary = self::buildDailySummary($date);
        $title = "Hermes Daily Report — {$date->translatedFormat('d F Y')}";
        $message = self::formatMessage($summary, $date->translatedFormat('d M Y'));

        foreach (self::recipientsByRole() as $user) {
            $already = $user->notifications()
                ->whereDate('created_at', $date->toDateString())
                ->whereNotNull('data')
                ->latest()
                ->limit(20)
                ->get(['data'])
                ->contains(fn ($n) => ($n->data['kind'] ?? null) === 'hermes_daily_report' && ($n->data['report_date'] ?? null) === $date->toDateString());

            if ($already) {
                continue;
            }

            $user->notify(new HermesDailyReportNotification(
                title: $title,
                message: $message,
                summary: $summary,
                reportDate: $date->toDateString(),
            ));
        }
    }

    private static function shouldReceiveReport(User $user): bool
    {
        $roles = trim((string) env('HERMES_REPORT_ROLES', 'owner,it'));
        $allowed = array_filter(array_map('trim', explode(',', $roles)), static fn ($r) => $r !== '');
        if (empty($allowed)) {
            $allowed = ['owner', 'it'];
        }

        return in_array($user->role, $allowed, true);
    }
}
