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
Kamu AI Orchestrator. Jawab HANYA JSON valid, tanpa markdown, tanpa penjelasan di luar JSON.
Semua teks dalam Bahasa Indonesia. Format: {"objectives":[{"title":"...","description":"...","priority":"Urgent|Penting","omset_target":0,"key_results":[{"title":"...","source":"auto|manual|kartu","metric":"view|subscriber|null","target":0,"unit":"angka|rupiah|persen","priority":"Urgent|Penting","kartu":[{"judul":"...","description":"...","pic":"nama","deadline":"YYYY-MM-DD","board":"key_board"}]}]}]}
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
     * OCR struk/nota: kirim gambar ke 9router (model vision) dan minta JSON transaksi.
     * Dipakai halaman Pembukuan untuk prefill form dari foto struk (user tetap meninjau).
     *
     * @param  string  $base64  Isi gambar (base64, tanpa prefix data URI).
     * @param  string  $mime    MIME gambar (image/jpeg, image/png, image/webp).
     * @param  array   $kategori Daftar kategori valid untuk membatasi pilihan AI.
     * @return array { ok: bool, data?: {type,category,amount_idr,date,description}, error?: string }
     */
    public function bacaStruk(string $base64, string $mime, array $kategori = []): array
    {
        $config = config('services.9router');

        if (empty($config['url']) || empty($config['token'])) {
            return ['ok' => false, 'error' => '9router belum dikonfigurasi (NINEROUTER_URL/NINEROUTER_TOKEN di .env).'];
        }

        $daftarKategori = $kategori ? implode(', ', $kategori) : 'bebas';
        $prompt = <<<PROMPT
        Baca struk/nota pada gambar ini. Jawab HANYA JSON valid tanpa markdown, format:
        {"type":"pemasukan|pengeluaran","category":"...","amount_idr":0,"date":"YYYY-MM-DD","description":"..."}
        Aturan:
        - Struk belanja/pembelian = "pengeluaran". Bukti penjualan/pemasukan = "pemasukan".
        - amount_idr = TOTAL akhir yang dibayar, angka bulat rupiah tanpa titik/koma/simbol.
        - date = tanggal transaksi di struk (format YYYY-MM-DD). Kalau tak terbaca, isi "".
        - category = pilih paling cocok dari daftar ini: {$daftarKategori}. Kalau ragu pakai "Biaya Operasional".
        - description = nama toko/merchant atau ringkasan singkat belanja.
        Semua teks Bahasa Indonesia.
        PROMPT;

        try {
            $res = Http::timeout($config['timeout'])
                ->withToken($config['token'])
                ->withoutVerifying()
                ->post(rtrim($config['url'], '/') . '/chat/completions', [
                    'model'       => $config['chatgpt_model'],
                    'temperature' => 0, // deterministik untuk ekstraksi
                    'messages'    => [[
                        'role'    => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$base64}"]],
                        ],
                    ]],
                ]);

            if (! $res->successful()) {
                Log::warning("9router OCR HTTP {$res->status()}", ['body' => $res->body()]);

                return ['ok' => false, 'error' => 'AI gagal membaca struk (HTTP ' . $res->status() . ').'];
            }

            $data = $this->parseJson($res->json('choices.0.message.content') ?? '');

            if (empty($data)) {
                return ['ok' => false, 'error' => 'AI tidak mengembalikan data yang bisa dibaca dari struk.'];
            }

            // Bersihkan & batasi ke nilai valid — jangan percaya output mentah LLM.
            $tanggal = (string) ($data['date'] ?? '');

            return ['ok' => true, 'data' => [
                'type'        => in_array($data['type'] ?? '', ['pemasukan', 'pengeluaran'], true) ? $data['type'] : 'pengeluaran',
                'category'    => (string) ($data['category'] ?? ''),
                'amount_idr'  => (int) round((float) ($data['amount_idr'] ?? 0)),
                'date'        => preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) ? $tanggal : '',
                'description' => (string) ($data['description'] ?? ''),
            ]];
        } catch (\Throwable $e) {
            Log::warning('9router OCR error: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Gagal menghubungi AI: ' . $e->getMessage()];
        }
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
