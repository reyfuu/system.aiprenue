<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InsightAccount;
use App\Models\InsightContent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Pintu masuk agen Insight (cron di VPS) → menyimpan metrik Instagram & YouTube.
 *
 * Bukan route web: tak ada sesi & tak lewat EnsureMenuAccess — gerbangnya bearer
 * token (INSIGHT_AGENT_TOKEN). Polanya sengaja meniru ScriptIngestController yang
 * sudah terbukti: aplikasi Laravel-nya "bodoh", hanya menerima & menyimpan. Ia
 * tak pernah memanggil API Google/Meta sendiri — rahasianya cukup ada di VPS.
 */
class InsightIngestController extends Controller
{
    public function store(Request $request)
    {
        $this->pastikanTokenSah($request);

        // Dua koleksi terpisah karena mengisi dua tabel dengan kunci idempotensi
        // berbeda. Keduanya opsional per-request (agen boleh kirim salah satu saja),
        // tapi minimal satu harus ada — dijaga tepat setelah validasi.
        $data = $request->validate([
            'contents'                             => ['sometimes', 'array', 'max:500'],
            'contents.*.platform'                  => ['required', Rule::in(array_keys(InsightContent::PLATFORMS))],
            'contents.*.content_id'                => ['required', 'string', 'max:255'],
            'contents.*.judul'                     => ['nullable', 'string'],
            'contents.*.url'                       => ['nullable', 'string', 'max:1024'],
            'contents.*.content_type'              => ['nullable', 'string', 'max:50'],
            'contents.*.published_at'              => ['nullable', 'date'],
            'contents.*.views'                     => ['nullable', 'integer', 'min:0'],
            'contents.*.reach'                     => ['nullable', 'integer', 'min:0'],
            'contents.*.impressions'               => ['nullable', 'integer', 'min:0'],
            'contents.*.likes'                     => ['nullable', 'integer', 'min:0'],
            'contents.*.comments'                  => ['nullable', 'integer', 'min:0'],
            'contents.*.shares'                    => ['nullable', 'integer', 'min:0'],
            'contents.*.saves'                     => ['nullable', 'integer', 'min:0'],
            'contents.*.watch_time_seconds'        => ['nullable', 'integer', 'min:0'],
            'contents.*.avg_view_duration_seconds' => ['nullable', 'integer', 'min:0'],
            'contents.*.avg_view_percentage'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contents.*.followers_gained'          => ['nullable', 'integer'], // signed: YT bisa negatif

            'accounts'                 => ['sometimes', 'array', 'max:100'],
            'accounts.*.platform'      => ['required', Rule::in(array_keys(InsightContent::PLATFORMS))],
            'accounts.*.akun'          => ['required', 'string', 'max:255'],
            'accounts.*.nama_akun'     => ['nullable', 'string', 'max:255'],
            'accounts.*.tanggal'       => ['required', 'date'],
            'accounts.*.followers'     => ['nullable', 'integer', 'min:0'],
            'accounts.*.media_count'   => ['nullable', 'integer', 'min:0'],
            'accounts.*.reach'         => ['nullable', 'integer', 'min:0'],
            'accounts.*.impressions'   => ['nullable', 'integer', 'min:0'],
            'accounts.*.profile_views' => ['nullable', 'integer', 'min:0'],
            'accounts.*.link_clicks'   => ['nullable', 'integer', 'min:0'],
        ]);

        abort_if(empty($data['contents']) && empty($data['accounts']), 422,
            'Tak ada yang dikirim: sertakan minimal salah satu dari "contents" atau "accounts".');

        // Idempoten lewat updateOrCreate pada kunci unik masing-masing tabel —
        // BUKAN replace-paket seperti Script. Insight menumpuk lintas waktu: cron
        // menarik ulang konten yang sama tiap hari untuk memperbarui angkanya, jadi
        // baris yang cocok di-UPDATE, bukan diduplikasi. Transaksi supaya satu
        // request tak pernah setengah-masuk kalau gagal di tengah.
        $hasil = DB::transaction(function () use ($data) {
            $konten = 0;
            foreach ($data['contents'] ?? [] as $c) {
                InsightContent::updateOrCreate(
                    ['platform' => $c['platform'], 'content_id' => $c['content_id']],
                    Arr::except($c, ['platform', 'content_id']),
                );
                $konten++;
            }

            $akun = 0;
            foreach ($data['accounts'] ?? [] as $a) {
                InsightAccount::updateOrCreate(
                    [
                        'platform' => $a['platform'],
                        'akun'     => $a['akun'],
                        // `tanggal` di-cast 'date' → tersimpan datetime awal-hari
                        // ('2026-07-20 00:00:00'). Cocokkan pakai Carbon, bukan string
                        // 'Y-m-d': string mentah tak match '... 00:00:00' sehingga cron
                        // yang menarik ulang hari yang sama memicu pelanggaran unique.
                        'tanggal'  => Carbon::parse($a['tanggal']),
                    ],
                    Arr::except($a, ['platform', 'akun', 'tanggal']),
                );
                $akun++;
            }

            return ['contents' => $konten, 'accounts' => $akun];
        });

        return response()->json(['ok' => true] + $hasil, 201);
    }

    /**
     * Gerbang token. 503 vs 401 sengaja dibedakan (pelajaran dari ScriptIngest):
     * 503 = token belum diisi di server (masalah konfigurasi), 401 = token dikirim
     * tapi salah. Tanpa pembedaan ini, memasang di server jadi menebak-nebak.
     */
    private function pastikanTokenSah(Request $request): void
    {
        $token = (string) config('services.insight_agent.token');

        abort_if($token === '', 503,
            'Token agen belum dikonfigurasi di server. Isi INSIGHT_AGENT_TOKEN di .env lalu jalankan: php artisan optimize:clear');

        // hash_equals: bandingkan dgn waktu tetap, jangan `===` (bocor lewat selisih waktu).
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401, 'Token tidak sah.');
    }
}
