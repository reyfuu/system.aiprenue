<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Satu baris = satu konten (post/reel/video/short) beserta metriknya. */
#[Fillable([
    'platform', 'content_id', 'judul', 'url', 'content_type', 'published_at',
    'views', 'reach', 'impressions', 'likes', 'comments', 'shares', 'saves',
    'watch_time_seconds', 'avg_view_duration_seconds', 'avg_view_percentage',
    'followers_gained',
])]
class InsightContent extends Model
{
    public const PLATFORMS = ['instagram' => 'Instagram', 'youtube' => 'YouTube'];

    /** Bobot content_score, langsung dari spesifikasi. Jumlahnya wajib 1.00 —
     *  ada tes yang menjaga itu, supaya mengubah satu bobot tanpa menyesuaikan
     *  yang lain tidak diam-diam menggeser seluruh skala skor. */
    public const BOBOT = [
        'views'         => 0.30,
        'engagement'    => 0.25,
        'share'         => 0.20,
        'save_or_watch' => 0.15,
        'follower_gain' => 0.10,
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'avg_view_percentage' => 'float'];
    }

    /**
     * Penyebut untuk semua rate. Urutannya dari spesifikasi: reach dulu, mundur
     * ke impressions bila kosong. `views` jadi cadangan terakhir supaya konten
     * YouTube (yang tak punya reach maupun impressions) tetap dapat rate.
     *
     * Mengembalikan null — bukan 0 — kalau tak satu pun tersedia, supaya
     * pembaginya tak pernah nol dan rate-nya jujur "tak terhitung", bukan 0%.
     */
    public function basis(): ?int
    {
        return $this->reach ?: ($this->impressions ?: ($this->views ?: null));
    }

    /** Total interaksi. saves hanya ada di IG; null diperlakukan 0 di sini karena
     *  yang dijumlah memang "berapa interaksi yang terjadi". */
    public function totalInteractions(): int
    {
        return (int) $this->likes + (int) $this->comments + (int) $this->shares + (int) $this->saves;
    }

    /** Rate dalam persen, atau null bila basisnya tak ada. */
    public function rate(?int $pembilang): ?float
    {
        $basis = $this->basis();

        return ($basis === null || $pembilang === null) ? null : round($pembilang / $basis * 100, 2);
    }

    public function engagementRate(): ?float { return $this->rate($this->totalInteractions()); }
    public function saveRate(): ?float       { return $this->rate($this->saves); }
    public function shareRate(): ?float      { return $this->rate($this->shares); }
    public function commentRate(): ?float    { return $this->rate($this->comments); }

    /**
     * Komponen ke-4 skor: "seberapa berharga konten ini dianggap".
     * IG diukur lewat save_rate, YouTube lewat avg_view_percentage — dua hal
     * berbeda yang menjawab pertanyaan sama, sesuai spesifikasi.
     */
    public function saveOrWatch(): ?float
    {
        return $this->platform === 'youtube' ? $this->avg_view_percentage : $this->saveRate();
    }

    /**
     * content_score 0–100 untuk sekumpulan konten.
     *
     * ⚠️ KEPUTUSAN YANG TIDAK ADA DI SPESIFIKASI. Spesifikasi menyebut bobot
     * (views_score 0.30, dst) tapi tak pernah mendefinisikan bagaimana sebuah
     * angka mentah menjadi "score". Views bisa 12 atau 1.200.000 — tak ada
     * skala bawaan.
     *
     * Yang dipakai di sini: **normalisasi relatif terhadap yang terbaik di
     * kumpulan yang sedang dibandingkan**. Konten dengan views tertinggi dapat
     * 100 untuk komponen views, sisanya proporsional.
     *
     * Konsekuensi yang HARUS dipahami sebelum memakai angkanya:
     *  - Skor bersifat RELATIF, bukan absolut. Konten yang sama bisa berskor
     *    beda kalau dibandingkan dengan kumpulan yang berbeda.
     *  - Skor 100 berarti "terbaik di antara ini", bukan "sempurna".
     *  - Membandingkan skor antar periode TIDAK sah tanpa kumpulan yang sama.
     *
     * Kalau nanti butuh skor absolut (bisa dibandingkan lintas waktu), ganti
     * pembaginya dengan ambang tetap per platform — dan tulis dari mana ambang
     * itu berasal.
     */
    public static function beriSkor(Collection $konten): Collection
    {
        // Nilai mentah tiap komponen per konten.
        $komponen = $konten->map(fn (self $c) => [
            'model'         => $c,
            'views'         => (float) $c->views,
            'engagement'    => (float) ($c->engagementRate() ?? 0),
            'share'         => (float) ($c->shareRate() ?? 0),
            'save_or_watch' => (float) ($c->saveOrWatch() ?? 0),
            'follower_gain' => max(0.0, (float) $c->followers_gained),   // kehilangan follower tak menambah skor, tapi juga tak menghukum dua kali
        ]);

        // Puncak per komponen = pembagi normalisasi. Dijaga > 0 supaya tak ada
        // pembagian nol saat seluruh kumpulan bernilai 0 untuk satu komponen.
        $puncak = [];
        foreach (array_keys(self::BOBOT) as $k) {
            $puncak[$k] = max(0.000001, (float) $komponen->max($k));
        }

        return $komponen->map(function (array $c) use ($puncak) {
            $skor = 0.0;
            foreach (self::BOBOT as $k => $bobot) {
                $skor += ($c[$k] / $puncak[$k]) * 100 * $bobot;
            }
            $c['model']->skor = round($skor, 1);

            return $c['model'];
        });
    }
}
