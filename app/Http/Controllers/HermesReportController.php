<?php

namespace App\Http\Controllers;

use App\Notifications\HermesDailyReportNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HermesReportController extends Controller
{
    public function index(Request $request)
    {
        // Daily Report hanya untuk owner/it pada aplikasi ini.
        abort_unless($request->user()?->canSee('daily_report'), 403, 'Anda tidak punya akses ke halaman ini.');

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'in:all,read,unread'],
        ]);

        $query = $request->user()
            ->notifications()
            ->where('type', HermesDailyReportNotification::class)
            ->latest('created_at');

        if ($from = $filters['from'] ?? null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $filters['to'] ?? null) {
            $query->whereDate('created_at', '<=', $to);
        }

        if (($filters['status'] ?? 'all') === 'read') {
            $query->whereNotNull('read_at');
        } elseif (($filters['status'] ?? 'all') === 'unread') {
            $query->whereNull('read_at');
        }

        $reports = $query->paginate(12)->withQueryString()->through(function ($notification) {
            $data = (array) ($notification->data ?? []);
            $summary = $data['summary'] ?? [];

            return [
                'id' => $notification->id,
                'title' => $data['title'] ?? 'Hermes Daily Report',
                'message' => $data['message'] ?? '',
                'report_date' => $data['report_date'] ?? $notification->created_at?->toDateString(),
                'summary' => [
                    'orders_created' => (int) ($summary['orders']['created'] ?? 0),
                    'orders_created_value_idr' => (float) ($summary['orders']['created_value_idr'] ?? 0),
                    'orders_paid' => (int) ($summary['orders']['paid'] ?? 0),
                    'orders_paid_value_idr' => (float) ($summary['orders']['paid_value_idr'] ?? 0),
                    'absensi_hadir' => (int) ($summary['absensi']['hadir'] ?? 0),
                    'absensi_izin_pending' => (int) ($summary['absensi']['izin_dengan_status_menunggu'] ?? 0),
                    'crm_new' => (int) ($summary['crm']['new'] ?? 0),
                    'crm_won' => (int) ($summary['crm']['won_today'] ?? 0),
                    'crm_due_soon' => (int) ($summary['crm']['due_soon'] ?? 0),
                    'payroll_entries' => (int) ($summary['payroll']['entries'] ?? 0),
                    'payroll_net' => (float) ($summary['payroll']['net'] ?? 0),
                    'payroll_periods_updated' => (int) ($summary['payroll']['periods_updated'] ?? 0),
                    'pembukuan_in' => (float) ($summary['pembukuan']['pemasukan'] ?? 0),
                    'pembukuan_out' => (float) ($summary['pembukuan']['pengeluaran'] ?? 0),
                ],
                'read_at' => optional($notification->read_at)->toIso8601String(),
                'created_at' => optional($notification->created_at)->toIso8601String(),
            ];
        });

        return Inertia::render('DailyReport', [
            'reports' => $reports,
            'filters' => $filters,
        ]);
    }
}
