<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Orkestrator AI untuk menyusun OKR via 9router (proxy LLM lokal di VPS).
 *
 * Mengirim arahan bisnis ke ChatGPT, lalu memvalidasi & menyeimbangkan hasil
 * dengan Claude. Output-nya: daftar Objective + Key Result + usulan kartu
 * workstream yang siap ditinjau sebelum disimpan.
 *
 * Pola: seperti InsightIngestController & ScriptIngestController yang
 * post data ke VPS. Bedanya, ini panggilan sinkron karena user menunggu hasil.
 */
class AiOrchestrator
{
    /**
     * Prompt sistem yang diinjeksi ke setiap panggilan LLM. Menjelaskan
     * siapa AI, format output yang diharapkan, dan aturan bisnis.
     */
    private const SYSTEM_PROMPT = <<<'PROMPT'
Kamu adalah AI Orchestrator untuk perusahaan SKINKU — distributor B2B produk kecantikan dan perawatan
tubuh di Indonesia. Kamu bekerja sebagai panel tiga spesialis (CMO, CFO, COO) yang berkolaborasi
menyusun OKR (Objective & Key Result) kuartalan.

Tugasmu: membaca arahan dari pengguna lalu menghasilkan output JSON dengan struktur berikut:

{
  "objectives": [
    {
      "title": "Kalimat objective (singkat, terarah, bisa diukur)",
      "description": "Alasan dipilih, konteks, baseline data",
      "priority": "Urgent atau Penting",
      "omset_target": 0,
      "key_results": [
        {
          "title": "Kalimat KR yang spesifik dan terukur",
          "source": "auto|manual|kartu",
          "metric": "view|subscriber|null",
          "target": 0,
          "unit": "angka|rupiah|persen",
          "priority": "Urgent atau Penting",
          "kartu": [
            {
              "judul": "Judul kartu workstream",
              "description": "Deskripsi tugas yang harus dikerjakan",
              "pic": "Nama orang (jika disebut di arahan, kalau tidak: 'belum ditentukan')",
              "deadline": "YYYY-MM-DD (opsional)",
              "board": "key board kanban yang dipilih pengguna, atau 'todolist'"
            }
          ]
        }
      ]
    }
  ]
}

ATURAN PENTING:
1. source KR: "auto" jika realisasi bisa dihitung dari data Insight/Pembukuan, "manual" jika angka manual, "kartu" jika realisasi = jumlah kartu selesai.
2. metric KR: untuk source "auto", pilih "view" (tayangan konten) atau "subscriber" (pengikut baru).
3. unit: "angka" untuk jumlah, "rupiah" untuk rupiah, "persen" untuk persentase.
4. priority: hanya "Urgent" (merah) atau "Penting" (biru). Objective boleh punya, KR boleh punya.
5. Tiap KR source=kartu WAJIB punya minimal 1 kartu workstream.
6. Target omzet hanya di Objective, bukan di KR.
7. Maksimal: 5 Objective, masing-masing maksimal 5 Key Result, masing-masing KR maksimal 5 kartu.
8. Jawaban HANYA JSON yang valid. Tanpa markdown, tanpa penjelasan di luar JSON.
9. Semua teks dalam Bahasa Indonesia.
PROMPT;

    /**
     * Susun usulan OKR berdasarkan arahan user.
     *
     * @param  array  $input  { jenis_periode, tahun, kuartal, level_okr, arahan, papan_kanban }
     * @return array  { objectives: [...], logs: [...] }
     */
    public function susun(array $input): array
    {
        $config = config('services.9router');

        if (empty($config['url'])) {
            return $this->gagal('URL 9router belum diatur. Isi NINEROUTER_URL di .env (contoh: http://127.0.0.1:8080/v1).');
        }
        if (empty($config['token'])) {
            return $this->gagal('Token 9router belum diatur. Isi NINEROUTER_TOKEN di .env.');
        }

        $prompt = $this->bangunPrompt($input);

        $draft = $this->panggilLlm(
            $config['url'],
            $config['token'],
            $config['chatgpt_model'],
            $prompt,
            $config['timeout']
        );

        if (empty($draft['objectives'])) {
            $debug = empty($draft) ? 'LLM tidak merespons (cek logs).' : 'Response LLM tidak mengandung objectives.';
            return $this->gagal('ChatGPT gagal: ' . $debug, $draft);
        }

        // Fase 2: Claude memvalidasi & menyeimbangkan (opsional — skip kalau model Claude kosong)
        $hasil = $draft;
        $logs = [['model' => $config['chatgpt_model'], 'waktu' => now()->toIso8601String()]];

        if (! empty($config['claude_model'])) {
            $reviewPrompt = $this->promptReview($prompt, $draft);
            $review = $this->panggilLlm(
                $config['url'],
                $config['token'],
                $config['claude_model'],
                $reviewPrompt,
                $config['timeout']
            );
            if (! empty($review['objectives'])) {
                $hasil = $review;
            }
            $logs[] = ['model' => $config['claude_model'], 'waktu' => now()->toIso8601String()];
        }

        return [
            'ok'         => true,
            'objectives' => $hasil['objectives'] ?? [],
            'raw'        => null, // jangan bocor raw response ke frontend
            'logs'       => $logs,
        ];
    }

    /**
     * Bangun prompt user dari input form Susun OKR dengan AI.
     */
    private function bangunPrompt(array $input): string
    {
        $periode = trim(($input['jenis_periode'] ?? 'Kuartalan') . ' ' . ($input['kuartal'] ?? '') . ' ' . ($input['tahun'] ?? ''));
        $level = $input['level_okr'] ?? 'Seluruh perusahaan';
        $arahan = $input['arahan'] ?? '';
        $board = ($input['papan_kanban'] ?? '') === 'AI pilih otomatis' ? 'todolist' : ($input['papan_kanban'] ?? 'todolist');

        return <<<PROMPT
Periode: {$periode}
Level OKR: {$level}
Papan Kanban utama: {$board}

Arahan pengguna:
{$arahan}

Susun OKR yang sesuai dengan arahan di atas. Pastikan setiap Key Result punya kartu (kartu)
yang siap dikerjakan oleh anggota tim. Papan kerja untuk semua kartu adalah "{$board}".
PROMPT;
    }

    /**
     * Prompt untuk Claude: review & seimbangkan draft ChatGPT.
     */
    private function promptReview(string $originalPrompt, array $draft): string
    {
        $encoded = json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
Berikut adalah usulan OKR yang disusun oleh model lain berdasarkan arahan ini:

ARAHAN ASLI:
{$originalPrompt}

USULAN DRAFT:
{$encoded}

Periksa usulan di atas. Kalau ada yang tidak seimbang (Objective terlalu berat/ringan,
target tidak realistis, KR tidak terukur, kartu tidak jelas) — perbaiki. Kalau sudah baik,
kembalikan apa adanya. Output HARUS JSON yang valid tanpa penjelasan apapun di luar JSON.
PROMPT;
    }

    /**
     * Panggil 9router API (OpenAI-compatible endpoint).
     */
    private function panggilLlm(string $baseUrl, string $token, string $model, string $prompt, int $timeout): array
    {
        try {
            $res = Http::timeout($timeout)
                ->withToken($token)
                ->withoutVerifying()
                ->post(rtrim($baseUrl, '/') . '/chat/completions', [
                    'model'       => $model,
                    'temperature' => 0.7,
                    'messages'    => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $res->successful()) {
                Log::warning("9router {$model} HTTP {$res->status()}", [
                    'body'   => $res->body(),
                    'url'    => rtrim($baseUrl, '/') . '/chat/completions',
                ]);

                return [];
            }

            $body = $res->json();
            $content = $body['choices'][0]['message']['content'] ?? '';

            if (empty($content)) {
                Log::warning("9router {$model} response kosong", ['body' => $body]);
            }

            return $this->parseJson($content);
        } catch (\Throwable $e) {
            Log::warning("9router {$model} error: " . $e->getMessage(), [
                'url' => rtrim($baseUrl, '/') . '/chat/completions',
                'model' => $model,
            ]);

            return [];
        }
    }

    /**
     * Parse konten LLM menjadi array PHP. Bersihkan markdown fences dulu.
     */
    private function parseJson(string $content): array
    {
        // Hapus ```json ... ``` wrapper kalau ada
        $content = trim($content);
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $content, $m)) {
            $content = $m[1];
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Gagal parse JSON dari LLM: ' . json_last_error_msg(), ['raw' => mb_substr($content, 0, 500)]);

            return [];
        }

        return $decoded;
    }

    /**
     * Response gagal — dipakai saat konfigurasi tidak lengkap atau LLM gagal.
     */
    private function gagal(string $pesan, array $draft = []): array
    {
        Log::warning('AiOrchestrator gagal: ' . $pesan);

        return [
            'ok'         => false,
            'error'      => $pesan,
            'objectives' => $draft['objectives'] ?? [],
            'raw'        => null,
            'logs'       => [],
        ];
    }
}
