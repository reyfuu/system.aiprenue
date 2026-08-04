<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\HermesDailyReportNotification;
use App\Support\Hermes;
use App\Support\Quarter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use App\Models\Category;
use App\Models\Pipeline;

class HermesReportController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        // Fitur chat Hermes hanya untuk owner & IT (sesuai menu daily_report).
        abort_unless($request->user()?->canSee('daily_report'), 403, 'Anda tidak punya akses ke chat Hermes.');

        $data = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
        ]);

        $message = trim((string) $data['message']);
        $intent = $this->deteksiIntentHermes($message);

        return match ($intent) {
            'create_okr' => $this->responseCreateOkr(),
            'detail_report' => $this->responseDetailReport(),
            'kanban_query' => $this->responseKanbanQuery($request->user(), $message),
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

        if (preg_match('/\bkanban\b|\bkaban\b|\bboard\b|\bkartu\b|\btugascard\b|\btodo\b|\bkanban\s*board\b/i', $normalized)) {
            return 'kanban_query';
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

    private function responseKanbanQuery(?User $actor, string $message): JsonResponse
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('pipelines') || !\Illuminate\Support\Facades\Schema::hasTable('categories')) {
            return response()->json([
                'ok' => false,
                'source' => 'system',
                'reply' => 'Data kanban belum siap saat ini (struktur tabel belum tersedia).',
            ], 502);
        }

        $boardKeys = Category::query()->where('type', 'kanban')->pluck('key')->all();
        if (empty($boardKeys)) {
            return response()->json([
                'ok' => true,
                'source' => 'system',
                'reply' => 'Saat ini belum ada board kanban yang aktif untuk dibaca.',
            ]);
        }

        $target = $this->extractUserMention($message, $actor);
        $targetLabel = $target ? $target->name : 'semua user';

        $query = Pipeline::query()
            ->with('assignee:id,name')
            ->whereIn('category', $boardKeys)
            ->whereNull('archived_at');

        if ($target) {
            $query->where('assigned_to', $target->id);
        } elseif ($actor) {
            $query->where(function ($q) use ($actor) {
                $q->whereNull('assigned_to')->orWhere('assigned_to', $actor->id);
            });
        }

        $active = (clone $query)
            ->where('done', false)
            ->orderByRaw('COALESCE(deadline, DATE(\'9999-12-31\'))')
            ->orderBy('id', 'desc')
            ->take(8)
            ->get(['id', 'endorse', 'deadline', 'progress', 'assigned_to']);

        $doneToday = (clone $query)
            ->where('done', true)
            ->whereDate('completed_at', today())
            ->orderByDesc('completed_at')
            ->take(8)
            ->get(['id', 'endorse', 'completed_at', 'progress', 'assigned_to']);

        $activeCount = (clone $query)->where('done', false)->count();
        $doneTodayCount = (clone $query)->where('done', true)->whereDate('completed_at', today())->count();
        $overdueCount = (clone $query)
            ->where('done', false)
            ->whereNotNull('deadline')
            ->where('deadline', '<', today())
            ->count();

        $lines = [
            "Siap, aku ambil ringkasan kanban untuk {$targetLabel} hari ini.",
            "Total aktif: {$activeCount} | Selesai hari ini: {$doneTodayCount} | Terlambat: {$overdueCount}.",
            '',
        ];

        if ($active->isNotEmpty()) {
            $lines[] = 'Masih aktif:';
            foreach ($active as $card) {
                $assignee = $card->assignee?->name ?? '-';
                $due = $card->deadline ? $card->deadline->format('d M') : 'tanpa deadline';
                $lines[] = "- #{$card->id} {$card->endorse} · {$card->progress} · deadline {$due} · PIC {$assignee}";
            }
            $lines[] = '';
        }

        if ($doneToday->isNotEmpty()) {
            $lines[] = 'Selesai hari ini:';
            foreach ($doneToday as $card) {
                $assignee = $card->assignee?->name ?? '-';
                $doneAt = $card->completed_at ? $card->completed_at->format('H:i') : '—';
                $lines[] = "- #{$card->id} {$card->endorse} · {$card->progress} · selesai {$doneAt} · PIC {$assignee}";
            }
        }

        if (! $active->isNotEmpty() && ! $doneToday->isNotEmpty()) {
            $lines[] = 'Tidak ada item Kanban yang cocok dengan filter ini.';
        }

        return response()->json([
            'ok' => true,
            'source' => 'local',
            'reply' => implode(PHP_EOL, $lines),
        ]);
    }

    private function extractUserMention(string $message, ?User $actor): ?User
    {
        $normalized = strtolower(trim($message));
        if ($actor && preg_match('/\b(saya|aku|gue|gue|ku)\b/i', $normalized)) {
            return $actor;
        }

        $clean = preg_replace('/[^a-z0-9@._-\\s]/i', ' ', $normalized);
        $tokens = array_filter(preg_split('/\\s+/', (string) $clean), fn ($token) => strlen($token) >= 3);

        $stopWords = ['tugas', 'kerjaan', 'work', 'di', 'kanban', 'board', 'a', 'apa', 'apa saja', 'selesai', 'yang', 'mana', 'siapa', 'aja', 'saja', 'dari'];
        foreach ($tokens as $token) {
            if (in_array($token, $stopWords, true)) {
                continue;
            }

            $user = User::query()
                ->whereRaw('LOWER(name) LIKE ?', ["%{$token}%"])
                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$token}%"])
                ->first();

            if ($user) {
                return $user;
            }
        }

        return null;
    }

    private function responseHermesMessage(?int $userId, string $message, string $intent): JsonResponse
    {
        $config = config('services.hermes_agent');
        $token = (string) ($config['token'] ?? '');
        $baseUrl = trim((string) ($config['url'] ?? ''));
        $timeout = (int) ($config['timeout'] ?? 20);
        $chatPaths = $this->normalizeHermesChatPaths((string) ($config['chat_path'] ?? '/api/chat'));

        if ($token === '' || $baseUrl === '') {
            return response()->json([
                'ok' => false,
                'source' => 'system',
                'reply' => $this->fallbackReply($message),
            ]);
        }

        try {
            $resolved = null;
            $attempts = [];
            $lastStatus = null;

            foreach ($chatPaths as $chatPath) {
                $url = rtrim($baseUrl, '/') . '/' . ltrim($chatPath, '/');
                $res = Http::timeout($timeout)
                    ->withToken($token)
                    ->acceptJson()
                    ->post($url, [
                        'message' => $message,
                        'intent' => $intent,
                        'user_id' => $userId,
                        'source' => 'system',
                        'context' => [
                            'current_quarter' => Quarter::current(),
                            'kanban' => $this->kanbanContext(),
                        ],
                    ]);

                $attempts[] = $chatPath . ' => ' . $res->status();
                $lastStatus = $res->status();

                if ($res->status() !== 405) {
                    $resolved = $res;
                    break;
                }
            }

            $res = $resolved;

            if (! $res?->successful()) {
                if ($lastStatus === 405 && ! empty($attempts)) {
                    return response()->json([
                        'ok' => false,
                        'source' => 'system',
                        'reply' => "Hermes menolak jalur chat (HTTP 405) pada: " . implode(', ', $attempts),
                    ], 502);
                }

                return response()->json([
                    'ok' => false,
                    'source' => 'system',
                    'reply' => "Hermes tidak merespons (HTTP {$lastStatus}). ".$this->fallbackReply($message),
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

    private function kanbanContext(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pipelines') || ! \Illuminate\Support\Facades\Schema::hasTable('categories')) {
            return [
                'summary' => 'Data kanban tidak tersedia (table belum ada).',
                'total_active' => 0,
                'total_done' => 0,
                'overdue' => 0,
                'due_next_3_days' => 0,
            ];
        }

        $boardKeys = Category::query()->where('type', 'kanban')->pluck('key')->all();
        $base = Pipeline::query()->whereIn('category', $boardKeys)->whereNull('archived_at');

        return [
            'summary' => "{$base->count()} kartu aktif di board Kanban.",
            'total_active' => (int) $base->count(),
            'total_done' => (int) (clone $base)->where('done', true)->count(),
            'overdue' => (int) (clone $base)
                ->whereNotNull('deadline')
                ->where('deadline', '<', today())
                ->where('done', false)
                ->count(),
            'due_next_3_days' => (int) (clone $base)
                ->whereNotNull('deadline')
                ->whereDate('deadline', '>=', today())
                ->whereDate('deadline', '<=', today()->addDays(3))
                ->where('done', false)
                ->count(),
        ];
    }

    private function normalizeHermesChatPaths(string $chatPathConfig): array
    {
        $paths = collect(preg_split('/\\s*,\\s*/', $chatPathConfig))
            ->map(static fn (string $path) => trim($path))
            ->filter()
            ->map(static fn (string $path) => trim($path, '/'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($paths)) {
            return ['api/chat'];
        }

        if (! in_array('api/chat', $paths, true)) {
            $paths[] = 'api/chat';
        }

        if (! in_array('chat', $paths, true)) {
            $paths[] = 'chat';
        }

        return $paths;
    }

    private function fallbackReply(string $message): string
    {
        return 'Koneksi Hermes lagi tidak stabil. Aku lanjut bantu sebisa mungkin lewat chat biasa—kirim pertanyaanmu langsung di sini, ya.';
    }

    public function index(Request $request)
    {
        // Daily Report hanya untuk owner/it.
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
