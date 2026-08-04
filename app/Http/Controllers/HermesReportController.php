<?php

namespace App\Http\Controllers;

use App\Notifications\HermesDailyReportNotification;
use App\Support\Hermes;
use App\Support\Quarter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class HermesReportController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        // Fitur chat Hermes untuk semua user yang login.
        abort_if(! $request->user(), 403, 'Anda harus login untuk memakai chatbot Hermes.');

        $data = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
        ]);

        $message = trim((string) $data['message']);
        $intent = $this->deteksiIntentHermes($message);

        return match ($intent) {
            'create_okr' => $this->responseCreateOkr(),
            'detail_report' => $this->responseDetailReport(),
            default => $this->responseHermesMessage($request->user()?->id, $message, $intent),
        };
    }

    private function deteksiIntentHermes(string $message): string
    {
        $normalized = strtolower($message);

        if (preg_match(
            '/\bbuat\b.*\b(okr|objective|tujuan)\b|\bcreate\b.*\b(okr|objective)\b|\bmake\b.*\b(okr|objective)\b|\bobjective\b/i',
            $normalized,
        )) {
            return 'create_okr';
        }

        if (preg_match('/\b(check|cek|lihat|tampilkan|detail)\b.*\breport\b|\bdetail\b.*\bhermes\b|\breport\b.*\bdetail\b/i', $normalized)) {
            return 'detail_report';
        }

        return 'chat';
    }

    private function responseCreateOkr()
    {
        return response()->json([
            'ok' => true,
            'source' => 'system',
            'reply' => 'Siap, aku bisa bantu mulai penyusunan OKR. Klik tombol di bawah untuk buka halaman OKR lalu isi Objective sesuai target hari ini.',
            'actions' => [
                ['label' => 'Buka halaman OKR', 'url' => route('okr.index')],
            ],
        ]);
    }

    private function responseDetailReport()
    {
        $summary = Hermes::buildDailySummary(Carbon::today());

        return response()->json([
            'ok' => true,
            'source' => 'system',
            'reply' => Hermes::formatMessage($summary, Carbon::today()->translatedFormat('d M Y')),
            'summary' => $summary,
        ]);
    }

    private function responseHermesMessage(?int $userId, string $message, string $intent): JsonResponse
    {
        $config = config('services.hermes_agent');
        $token = (string) ($config['token'] ?? '');
        $baseUrl = trim((string) ($config['url'] ?? ''));
        $chatPath = trim((string) ($config['chat_path'] ?? '/chat'), ' /');
        $timeout = (int) ($config['timeout'] ?? 20);

        if ($token === '' || $baseUrl === '') {
            return response()->json([
                'ok' => false,
                'source' => 'system',
                'reply' => $this->fallbackReply($message),
            ]);
        }

        try {
            $res = Http::timeout($timeout)
                ->withToken($token)
                ->acceptJson()
                ->post(rtrim($baseUrl, '/') . '/' . $chatPath, [
                    'message' => $message,
                    'intent' => $intent,
                    'user_id' => $userId,
                    'source' => 'system',
                    'context' => [
                        'current_quarter' => Quarter::current(),
                    ],
                ]);

            if (! $res->successful()) {
                return response()->json([
                    'ok' => false,
                    'source' => 'system',
                    'reply' => "Hermes tidak merespons (HTTP {$res->status()}). ".$this->fallbackReply($message),
                ], 502);
            }

            $body = (array) $res->json();
            $reply = trim((string) ($body['reply'] ?? $body['message'] ?? ''));

            if ($reply === '') {
                return response()->json([
                    'ok' => false,
                    'source' => 'system',
                    'reply' => $this->fallbackReply($message),
                ], 502);
            }

            return response()->json([
                'ok' => true,
                'source' => 'hermes',
                'reply' => $reply,
                'actions' => is_array($body['actions'] ?? null) ? $body['actions'] : [],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'source' => 'system',
                'reply' => 'Tidak bisa terhubung ke Hermes saat ini. '.$this->fallbackReply($message),
            ], 502);
        }
    }

    private function fallbackReply(string $message): string
    {
        return 'Coba klik salah satu opsi cepat: “Buat OKR” atau “Check detail report”. Jika tetap ingin ngobrol bebas, kirim pesan langsung di sini.';
    }

    public function index(Request $request)
    {
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
