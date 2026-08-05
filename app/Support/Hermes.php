<?php

namespace App\Support;

use App\Models\Absence;
use App\Models\Attendance;
use App\Models\InsightContent;
use App\Models\Mindmap;
use App\Models\Objective;
use App\Models\Order;
use App\Models\Pipeline;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Script;
use App\Models\User;
use App\Models\Transaction;
use App\Notifications\HermesDailyReportNotification;
use App\Support\ExchangeRate;
use App\Support\OkrMetrics;
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

        // ---- Kanban ----
        $kanbanActive = 0;
        $kanbanDoneToday = 0;
        $kanbanOverdue = 0;
        if (Schema::hasTable('pipelines') && Schema::hasTable('categories')) {
            $kanbanBoards = Pipeline::categories('kanban');
            $kBase = Pipeline::query()
                ->whereIn('category', array_keys($kanbanBoards))
                ->whereNull('archived_at');
            $kanbanActive    = (int) (clone $kBase)->where('done', false)->count();
            $kanbanDoneToday = (int) (clone $kBase)->where('done', true)->whereDate('completed_at', $dateStr)->count();
            $kanbanOverdue   = (int) (clone $kBase)->where('done', false)->whereNotNull('deadline')->where('deadline', '<', $date->startOfDay())->count();
        }

        // ---- OKR ----
        $okrObjectives = 0;
        $okrProgress = null;
        $okrTercapai = 0;
        if (Schema::hasTable('objectives') && Schema::hasTable('okr_periods')) {
            $q = Quarter::current();
            $realisasi = OkrMetrics::realisasi($q['year'], $q['quarter']);
            $objectives = Objective::forQuarter($q['year'], $q['quarter']);
            $okrObjectives = $objectives->count();
            $progressArr = $objectives->map(fn ($o) => $o->progress($realisasi))->filter(fn ($p) => $p !== null);
            $okrProgress = $progressArr->isEmpty() ? null : round($progressArr->average(), 1);
            $krs = $objectives->pluck('keyResults')->flatten(1);
            $okrTercapai = $krs->filter(fn ($kr) => $kr->percent($realisasi) >= 100)->count();
        }

        // ---- Insight ----
        $insightKonten = 0;
        $insightViews = 0;
        $insightFollowerGain = 0;
        if (Schema::hasTable('insight_contents')) {
            $insightKonten = (int) InsightContent::whereDate('published_at', $dateStr)->count();
            $insightViews  = (int) InsightContent::whereDate('published_at', $dateStr)->sum('views');
            $insightFollowerGain = (int) InsightContent::whereDate('published_at', $dateStr)->sum('followers_gained');
        }

        // ---- Mindmap & Script ----
        $mindmapUpdated = 0;
        $scriptNew = 0;
        if (Schema::hasTable('mindmaps')) {
            $mindmapUpdated = (int) Mindmap::whereDate('updated_at', $dateStr)->count();
        }
        if (Schema::hasTable('scripts')) {
            $scriptNew = (int) Script::whereDate('created_at', $dateStr)->count();
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
            'kanban' => [
                'active' => $kanbanActive,
                'done_today' => $kanbanDoneToday,
                'overdue' => $kanbanOverdue,
            ],
            'okr' => [
                'objectives' => $okrObjectives,
                'progress' => $okrProgress,
                'tercapai' => $okrTercapai,
            ],
            'insight' => [
                'konten_baru' => $insightKonten,
                'views' => $insightViews,
                'follower_gain' => $insightFollowerGain,
            ],
            'mindmap' => [
                'updated' => $mindmapUpdated,
            ],
            'script' => [
                'new' => $scriptNew,
            ],
        ];
    }

    public static function formatMessage(array $s, string $date): string
    {
        $toRupiah = fn (float $nominal) => 'Rp '.number_format($nominal, 0, ',', '.');
        $nl = PHP_EOL;

        $okrLine = '';
        if (! empty($s['okr'])) {
            $prog = $s['okr']['progress'] !== null ? $s['okr']['progress'].'%' : 'belum ada data';
            $okrLine = "{$nl}OKR: {$s['okr']['objectives']} objective aktif, rata-rata progres {$prog}, {$s['okr']['tercapai']} KR tercapai 100%.";
        }

        $insightLine = '';
        if (! empty($s['insight'])) {
            $insightLine = "{$nl}Insight: {$s['insight']['konten_baru']} konten tayang hari ini, {$s['insight']['views']} views, +{$s['insight']['follower_gain']} follower.";
        }

        $kanbanLine = '';
        if (! empty($s['kanban'])) {
            $kanbanLine = "{$nl}Kanban: {$s['kanban']['active']} tugas aktif, {$s['kanban']['done_today']} selesai hari ini, {$s['kanban']['overdue']} terlambat.";
        }

        $mindmapLine = ! empty($s['mindmap']) && $s['mindmap']['updated'] > 0
            ? "{$nl}Mindmap: {$s['mindmap']['updated']} peta diperbarui hari ini."
            : '';

        $scriptLine = ! empty($s['script']) && $s['script']['new'] > 0
            ? "{$nl}Script: {$s['script']['new']} naskah baru dibuat hari ini."
            : '';

        return "Laporan harian {$date}"
            ."{$nl}Order: {$s['orders']['created']} order masuk ({$toRupiah((float) $s['orders']['created_value_idr'])}), {$s['orders']['full']} lunas, {$s['orders']['dp']} DP."
            ."{$nl}Pembayaran diterima: {$s['orders']['paid']} transaksi ({$toRupiah((float) $s['orders']['paid_value_idr'])})."
            ."{$nl}Absensi: {$s['absensi']['hadir']} hadir, {$s['absensi']['izin_dengan_status_menunggu']} pengajuan izin menunggu persetujuan."
            ."{$nl}CRM/Sales: {$s['crm']['new']} lead baru, {$s['crm']['won_today']} deal won, {$s['crm']['due_soon']} deal jatuh tempo ≤7 hari."
            .($okrLine)
            .($kanbanLine)
            ."{$nl}Pembukuan: pemasukan {$toRupiah((float) $s['pembukuan']['pemasukan'])}, pengeluaran {$toRupiah((float) $s['pembukuan']['pengeluaran'])}."
            ."{$nl}Payroll: {$s['payroll']['entries']} entri dibuat, {$s['payroll']['periods_updated']} periode diperbarui."
            .($insightLine)
            .($mindmapLine)
            .($scriptLine);
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
