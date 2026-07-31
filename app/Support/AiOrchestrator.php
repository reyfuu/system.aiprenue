<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Helper OCR berbasis vision lewat 9router (proxy LLM lokal di VPS).
 *
 * Membaca gambar (struk/nota/foto barang) dan mengembalikan JSON untuk
 * prefill form — user tetap meninjau sebelum menyimpan. Dipakai halaman
 * Pembukuan (transaksi & inventaris).
 *
 * Catatan: penyusunan OKR dengan AI dipindah ke MCP; orkestrasi in-app dibuang.
 */
class AiOrchestrator
{
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

        $hasil = $this->ekstrakVisi($prompt, $base64, $mime);
        if (! ($hasil['ok'] ?? false)) {
            return $hasil;
        }

        // Bersihkan & batasi ke nilai valid — jangan percaya output mentah LLM.
        $data = $hasil['data'];
        $tanggal = (string) ($data['date'] ?? '');

        return ['ok' => true, 'data' => [
            'type'        => in_array($data['type'] ?? '', ['pemasukan', 'pengeluaran'], true) ? $data['type'] : 'pengeluaran',
            'category'    => (string) ($data['category'] ?? ''),
            'amount_idr'  => (int) round((float) ($data['amount_idr'] ?? 0)),
            'date'        => preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) ? $tanggal : '',
            'description' => (string) ($data['description'] ?? ''),
        ]];
    }

    /**
     * OCR inventaris: baca foto barang/nota aset dan minta JSON inventaris.
     * Dipakai halaman Pembukuan (modal inventaris) untuk prefill form.
     *
     * @param  string  $base64  Isi gambar (base64, tanpa prefix data URI).
     * @param  string  $mime    MIME gambar (image/jpeg, image/png, image/webp).
     * @return array { ok: bool, data?: {name,qty,unit_value_idr,month}, error?: string }
     */
    public function bacaInventaris(string $base64, string $mime): array
    {
        $prompt = <<<'PROMPT'
        Baca gambar barang/aset atau nota inventaris ini. Jawab HANYA JSON valid tanpa markdown, format:
        {"name":"...","qty":0,"unit_value_idr":0,"month":"YYYY-MM"}
        Aturan:
        - name = nama barang/aset utama pada gambar.
        - qty = jumlah unit, angka bulat. Kalau tak terbaca, isi 1.
        - unit_value_idr = harga/nilai per SATU unit, angka bulat rupiah tanpa titik/koma/simbol. Kalau yang tertera total, bagi dengan qty.
        - month = bulan snapshot (format YYYY-MM). Kalau tak terbaca, isi "".
        Semua teks Bahasa Indonesia.
        PROMPT;

        $hasil = $this->ekstrakVisi($prompt, $base64, $mime);
        if (! ($hasil['ok'] ?? false)) {
            return $hasil;
        }

        $data = $hasil['data'];
        $bulan = (string) ($data['month'] ?? '');

        return ['ok' => true, 'data' => [
            'name'           => (string) ($data['name'] ?? ''),
            'qty'            => max(0, (int) round((float) ($data['qty'] ?? 0))),
            'unit_value_idr' => (int) round((float) ($data['unit_value_idr'] ?? 0)),
            'month'          => preg_match('/^\d{4}-\d{2}$/', $bulan) ? $bulan : '',
        ]];
    }

    /**
     * Panggilan vision generik ke 9router: kirim prompt + gambar, balikan hasil
     * parse JSON mentah. Dipakai bersama oleh bacaStruk & bacaInventaris.
     *
     * @return array { ok: bool, data?: array, error?: string }
     */
    private function ekstrakVisi(string $prompt, string $base64, string $mime): array
    {
        $config = config('services.9router');

        if (empty($config['url']) || empty($config['token'])) {
            return ['ok' => false, 'error' => '9router belum dikonfigurasi (NINEROUTER_URL/NINEROUTER_TOKEN di .env).'];
        }

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

                return ['ok' => false, 'error' => 'AI gagal membaca gambar (HTTP ' . $res->status() . ').'];
            }

            $data = $this->parseJson($res->json('choices.0.message.content') ?? '');

            if (empty($data)) {
                return ['ok' => false, 'error' => 'AI tidak mengembalikan data yang bisa dibaca dari gambar.'];
            }

            return ['ok' => true, 'data' => $data];
        } catch (\Throwable $e) {
            Log::warning('9router OCR error: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Gagal menghubungi AI: ' . $e->getMessage()];
        }
    }

}
