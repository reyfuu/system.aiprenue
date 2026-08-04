<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Hermes;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Pintu masuk agen Hermes (cron di VPS) → trigger laporan harian Hermes.
 *
 * Endpoint ini tidak masuk route web karena:
 * 1) tak butuh sesi (tanpa CSRF),
 * 2) tidak lewat EnsureMenuAccess,
 * 3) dijaga bearer token + token env.
 */
class HermesReportController extends Controller
{
    public function dailyReport(Request $request)
    {
        $this->pastikanTokenSah($request);

        $data = $request->validate([
            'date' => ['sometimes', 'date'],
        ]);

        $date = Carbon::parse($data['date'] ?? null);
        $summary = Hermes::buildDailySummary($date);
        $recipientCount = Hermes::recipientsByRole()->count();

        Hermes::sendToConfiguredRoles($date);

        return response()->json([
            'ok' => true,
            'date' => $date->toDateString(),
            'sent_to' => $recipientCount,
            'summary' => $summary,
        ], 201);
    }

    private function pastikanTokenSah(Request $request): void
    {
        $token = (string) config('services.hermes_agent.token');

        abort_if($token === '', 503,
            'Token agen belum dikonfigurasi di server. Isi HERMES_AGENT_TOKEN di .env lalu jalankan: php artisan optimize:clear');

        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401, 'Token tidak sah.');
    }
}
