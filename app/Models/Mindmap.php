<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mindmap extends Model
{
    protected $fillable = ['user_id', 'title', 'data'];

    protected $casts = ['data' => 'array']; // JSON node mind-elixir ↔ array PHP

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Template mindmap siap pakai.
     *
     *  Bentuknya kerangka kerja umum (brainstorm, SWOT, alur produksi) yang
     *  disesuaikan ke bisnis di sini — BUKAN salinan template berbayar milik
     *  produk lain. `cabang` = judul cabang => daftar anaknya.
     *
     *  'kosong' sengaja pertama & isinya nol: itu perilaku lama (mindmap baru
     *  tanpa isi), jadi tombol lama tetap punya padanan.
     */
    public static function templates(): array
    {
        return [
            'kosong' => [
                'label' => 'Kosong',
                'desc'  => 'Mulai dari nol, satu node saja.',
                'root'  => 'Mindmap Baru',
                'cabang' => [],
            ],
            'brainstorm_konten' => [
                'label' => 'Brainstorm Konten',
                'desc'  => 'Kumpulkan ide konten: tema, format, hook, dan CTA.',
                'root'  => 'Brainstorm Konten',
                'cabang' => [
                    'Tema'   => ['Behind the scene', 'Testimoni klien', 'Edukasi bahan', 'Tren musiman'],
                    'Format' => ['Reels', 'Story', 'Carousel', 'Live'],
                    'Hook'   => ['Pertanyaan', 'Angka mengejutkan', 'Before after'],
                    'CTA'    => ['Konsultasi gratis', 'Cek link bio', 'Simpan & bagikan'],
                ],
            ],
            'swot' => [
                'label' => 'SWOT Brand',
                'desc'  => 'Petakan kekuatan, kelemahan, peluang, dan ancaman.',
                'root'  => 'SWOT Brand',
                'cabang' => [
                    'Kekuatan'  => ['Kualitas jahit', 'Pelayanan personal'],
                    'Kelemahan' => ['Kapasitas produksi', 'Waktu pengerjaan'],
                    'Peluang'   => ['Musim wisuda', 'Kolaborasi kreator'],
                    'Ancaman'   => ['Kompetitor harga murah', 'Tren berubah cepat'],
                ],
            ],
            'kampanye' => [
                'label' => 'Rencana Kampanye',
                'desc'  => 'Susun kampanye: tujuan, audiens, pesan, kanal, ukuran.',
                'root'  => 'Rencana Kampanye',
                'cabang' => [
                    'Tujuan'  => ['Awareness', 'Leads masuk', 'Closing'],
                    'Audiens' => ['Calon pengantin', 'Wisudawan', 'Acara kantor'],
                    'Pesan'   => ['Serasa punya penjahit pribadi', 'Garansi fitting pas'],
                    'Kanal'   => ['Instagram', 'TikTok', 'WhatsApp'],
                    'Ukuran'  => ['Jumlah DM', 'Biaya per lead', 'Closing rate'],
                ],
            ],
            'alur_produksi' => [
                'label' => 'Alur Produksi',
                'desc'  => 'Runtut dari order masuk sampai barang diterima.',
                'root'  => 'Alur Produksi',
                'cabang' => [
                    'Order masuk' => ['Konsultasi', 'Free sketch', 'DP'],
                    'Produksi'    => ['Ambil ukuran', 'Potong kain', 'Jahit', 'Fitting'],
                    'Finishing'   => ['QC', 'Setrika & kemas'],
                    'Kirim'       => ['Pelunasan', 'Kirim / ambil', 'Minta testimoni'],
                ],
            ],
        ];
    }

    /** Ubah template jadi struktur data mind-elixir.
     *
     *  Mengembalikan null untuk 'kosong' — biar frontend memakai
     *  MindElixir.new() seperti sebelumnya, bukan struktur setengah jadi.
     *
     *  `direction` diselang-seling 0/1 supaya cabang terbagi kiri-kanan; tanpa
     *  itu mind-elixir menumpuk semuanya di satu sisi & templatenya terlihat
     *  berat sebelah.
     */
    public static function dataDariTemplate(string $key, string $judul): ?array
    {
        $t = self::templates()[$key] ?? null;
        if (! $t || ! $t['cabang']) {
            return null;
        }

        $n = 0;
        $uid = function () use (&$n) {
            // Id cukup unik di dalam satu peta; mind-elixir cuma memakainya
            // untuk menautkan node, tak pernah dibandingkan antar-mindmap.
            return 'me'.base_convert((string) (++$n), 10, 36).substr(md5((string) mt_rand()), 0, 6);
        };

        $anak = [];
        $i = 0;
        foreach ($t['cabang'] as $cabang => $daun) {
            $anak[] = [
                'id'        => $uid(),
                'topic'     => $cabang,
                'direction' => $i++ % 2,          // 0 = kiri, 1 = kanan
                'expanded'  => true,
                'children'  => array_map(fn ($d) => [
                    'id'    => $uid(),
                    'topic' => $d,
                ], $daun),
            ];
        }

        return [
            'nodeData' => [
                'id'       => $uid(),
                'topic'    => trim($judul) ?: $t['root'],
                'root'     => true,
                'expanded' => true,
                'children' => $anak,
            ],
            'arrows'    => [],
            'summaries' => [],
        ];
    }
}
