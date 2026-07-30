<?php

namespace App\Http\Controllers;

use App\Models\InsightAccount;
use App\Models\InsightContent;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Menu Insight — performa konten Instagram & YouTube berdampingan.
 *
 * Pertanyaan yang halaman ini harus jawab (urut kepentingan):
 *   1. Konten mana yang menang?      → tabel Top Content, diperingkat content_score
 *   2. Kenapa menang?                → kolom rate (share/save/comment) di tabel itu
 *   3. Mana yang bawa growth?        → kolom follower gain + grafik akun
 *
 * Yang SENGAJA tidak ada: kartu metrik yang tak menjawab salah satu dari itu.
 * "Dashboard rame" bukan tujuannya.
 */
class InsightController extends Controller
{
    public function index(Request $request)
    {
        // Filter platform. 'semua' = bandingkan lintas platform — justru itu guna
        // tabel unified-nya. Kunci ngawur jatuh ke 'semua', bukan hasil kosong:
        // tabel kosong terbaca "belum ada data" padahal parameternya yang salah.
        $platform = (string) $request->query('platform', 'semua');
        if (! array_key_exists($platform, InsightContent::PLATFORMS)) {
            $platform = 'semua';
        }

        $konten = InsightContent::query()
            ->when($platform !== 'semua', fn ($q) => $q->where('platform', $platform))
            ->orderByDesc('published_at')
            ->limit(200)   // batas atas skoring; UI menyebutkan kalau terpotong
            ->get();

        // Skor dihitung terhadap kumpulan yang SEDANG dilihat — konsekuensi dari
        // normalisasi relatif (lihat InsightContent::beriSkor). Karena itu skor
        // bisa berubah saat filter platform diganti, dan itu memang disengaja:
        // "terbaik di antara konten Instagram" ≠ "terbaik di antara semuanya".
        $berskor = InsightContent::beriSkor($konten)->sortByDesc('skor')->values();

        return Inertia::render('Insight', [
            'platforms' => InsightContent::PLATFORMS,
            'aktif'     => $platform,

            // Kartu atas — hanya yang menjawab pertanyaan di docblock.
            'ringkasan' => [
                'konten'       => $konten->count(),
                'views'        => (int) $konten->sum('views'),
                'reach'        => (int) $konten->sum('reach'),
                'watchJam'     => round($konten->sum('watch_time_seconds') / 3600, 1),
                'followerGain' => (int) $konten->sum('followers_gained'),
                // Rata-rata engagement dihitung dari total, BUKAN rata-rata dari
                // rate tiap konten. Rata-rata dari rate memberi bobot sama pada
                // konten yang dilihat 10 orang dan yang dilihat 100.000 — itu
                // membuat satu konten sepi berperforma "bagus" bisa menaikkan
                // angkanya secara menyesatkan.
                'engagement'   => $this->engagementGabungan($konten),
            ],

            'konten' => $berskor->map(fn (InsightContent $c) => [
                'id'            => $c->id,
                'platform'      => $c->platform,
                'judul'         => $c->judul,
                'url'           => $c->url,
                'tipe'          => $c->content_type,
                'terbit'        => $c->published_at?->format('Y-m-d'),
                'views'         => $c->views,
                'reach'         => $c->reach,
                'likes'         => $c->likes,
                'comments'      => $c->comments,
                'shares'        => $c->shares,
                'saves'         => $c->saves,
                'watchPersen'   => $c->avg_view_percentage,
                'followerGain'  => $c->followers_gained,
                'engagementRate' => $c->engagementRate(),
                'shareRate'     => $c->shareRate(),
                'saveRate'      => $c->saveRate(),
                'skor'          => $c->skor,
            ]),

            // Grafik pertumbuhan akun — 60 hari terakhir.
            'akun' => InsightAccount::query()
                ->when($platform !== 'semua', fn ($q) => $q->where('platform', $platform))
                ->where('tanggal', '>=', now()->subDays(60))
                ->orderBy('tanggal')
                ->get(['platform', 'nama_akun', 'tanggal', 'followers', 'profile_views'])
                ->map(fn ($a) => [
                    'platform'     => $a->platform,
                    'namaAkun'     => $a->nama_akun,
                    'tanggal'      => $a->tanggal->format('Y-m-d'),
                    'followers'    => $a->followers,
                    'profileViews' => $a->profile_views,
                ]),
        ]);
    }

    /** Engagement rate gabungan = total interaksi / total basis. Lihat alasannya
     *  di komentar pemanggilnya. */
    private function engagementGabungan($konten): ?float
    {
        $interaksi = $konten->sum(fn (InsightContent $c) => $c->totalInteractions());
        $basis     = $konten->sum(fn (InsightContent $c) => $c->basis() ?? 0);

        return $basis > 0 ? round($interaksi / $basis * 100, 2) : null;
    }
}
