<?php

namespace App\Http\Controllers;

use App\Models\BoardQuarterTarget;
use App\Models\Category;
use App\Models\Pipeline;
use App\Support\KinerjaOrang;
use App\Support\Quarter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * KPI board Kanban per kuartal: target "berapa kartu selesai" + rekap
 * ketepatan waktu.
 *
 *  Terpisah dari OkrController secara sengaja. OKR memuat omset & pertumbuhan
 *  audiens — angka tingkat perusahaan yang hanya untuk owner & manager. Yang
 *  ada di sini murni operasional papan, jadi boleh dilihat tim yang memang
 *  mengelola board. Selama keduanya satu halaman, memberi akses ke yang satu
 *  berarti memberi akses ke yang lain.
 *
 *  Kuartal dipilih lewat ?q=YYYY-Qn; tanpa itu memakai kuartal berjalan.
 */
class KpiController extends Controller
{
    public function index(Request $request)
    {
        // ?q ngawur diabaikan (jatuh ke kuartal berjalan), bukan bikin 4xx.
        $q = Quarter::parse($request->query('q')) ?? Quarter::current();
        [$year, $quarter] = [$q['year'], $q['quarter']];
        [$start, $end] = Quarter::range($year, $quarter);

        $user = $request->user();

        // ---- Tab "Per Board": hanya peran pengelola ----
        // null (bukan array kosong) supaya Vue bisa membedakan "tak berhak"
        // dari "berhak tapi belum ada board".
        $bolehBoard = $user->canManage();
        $board = $bolehBoard ? self::ringkasanBoard($year, $quarter) : null;

        // ---- Tab "Per Orang": owner & manager melihat semua ----
        // Peran lain hanya barisnya sendiri, DISARING DI SINI. Menyaringnya di
        // Vue berarti nama & angka rekan kerja tetap terkirim & terbaca di
        // source halaman — persis kebocoran yang sedang ditutup di Kanban.
        $bolehSemuaOrang = in_array($user->role, ['owner', 'manager'], true);
        $orang = KinerjaOrang::untukKuartal($year, $quarter);
        if (! $bolehSemuaOrang) {
            $orang = array_values(array_filter($orang, fn ($b) => $b['user_id'] === $user->id));
        }

        return Inertia::render('Kpi', [
            'quarter' => ['year' => $year, 'quarter' => $quarter, 'key' => $year.'-Q'.$quarter, 'label' => Quarter::label($year, $quarter)],
            'quarterOptions' => Quarter::options(),
            'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'board' => $board,
            'total' => $bolehBoard ? self::totalLintasBoard($board) : null,
            'orang' => $orang,
            // 'semua' = tabel tim; 'sendiri' = kartu "Rapor saya". Dikirim
            // eksplisit supaya Vue tak perlu menebak dari jumlah baris —
            // owner yang kebetulan cuma punya satu orang di tim tetap harus
            // melihat tabel, bukan rapor pribadi.
            'scope' => $bolehSemuaOrang ? 'semua' : 'sendiri',
            'namaSaya' => $user->name,
            'canManage' => $bolehBoard,
        ]);
    }

    /**
     * Ringkasan tiap board Kanban pada satu kuartal.
     *
     *  Kartu dimasukkan kuartal berdasarkan DEADLINE-nya — sama dgn filter di
     *  halaman Kanban, supaya angka di dua halaman ini tak pernah berselisih.
     *  Kartu tanpa deadline tidak masuk kuartal mana pun; itu konsekuensi yang
     *  dipilih, karena target kuartalan hanya bermakna untuk pekerjaan yang
     *  punya batas waktu.
     *
     *  Kartu terarsip tetap dihitung: mengarsipkan kartu yang sudah rampung
     *  adalah kebiasaan merapikan papan, bukan pembatalan pekerjaan — kalau
     *  dibuang, capaian board menyusut tiap kali papannya dibersihkan.
     *
     *  static & public karena PipelineController memakai rumus yang sama untuk
     *  panel kuartal di Kanban. Dikembarkan lewat pemanggilan, bukan disalin.
     */
    public static function ringkasanBoard(int $year, int $quarter): array
    {
        $boards = Category::where('type', 'kanban')->orderBy('name')->get();
        $targets = BoardQuarterTarget::where('year', $year)->where('quarter', $quarter)
            ->get()->keyBy('board_key');

        return $boards->map(function (Category $b) use ($year, $quarter, $targets) {
            $row = $targets->get($b->key);

            // Target dioper dari koleksi yang sudah diambil sekali di atas —
            // kalau statistik() mencarinya sendiri, halaman ini melakukan satu
            // query target per board (N+1) padahal semuanya sudah di tangan.
            return array_merge(self::statistik($b->key, $year, $quarter, (int) ($row->target_done ?? 0)), [
                'key' => $b->key,
                'name' => $b->name,
                'note' => $row->note ?? null,
                'set_by' => $row?->creator?->name,
            ]);
        })->all();
    }

    /** Angka satu board pada satu kuartal. Satu-satunya tempat rumus KPI
     *  ditulis — Kanban & halaman ini sama-sama lewat sini.
     *  $target boleh dioper bila pemanggil sudah punya (hindari N+1). */
    public static function statistik(string $boardKey, int $year, int $quarter, ?int $target = null): array
    {
        [$start, $end] = Quarter::range($year, $quarter);

        $kartu = Pipeline::where('category', $boardKey)
            ->whereBetween('deadline', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'deadline', 'completed_at']);

        $selesai = $kartu->whereNotNull('completed_at')->count();
        $target ??= (int) (BoardQuarterTarget::for($boardKey, $year, $quarter)?->target_done ?? 0);

        return [
            'total' => $kartu->count(),
            'done' => $selesai,
            'target' => $target,
            // null saat target belum ditetapkan: persentase terhadap 0 tak punya
            // arti, dan menampilkannya sbg 0% terbaca seolah timnya gagal
            // padahal targetnya memang belum ada.
            'percent' => $target > 0 ? round($selesai / $target * 100, 1) : null,
            'ketepatan' => self::hitungKetepatan($kartu),
        ];
    }

    /** Rekap ketepatan sekumpulan kartu. */
    public static function hitungKetepatan($kartu): array
    {
        $nilai = collect($kartu)->map(fn (Pipeline $p) => $p->ketepatan());
        $tepat = $nilai->filter(fn ($v) => $v === 'tepat')->count();
        $terlambat = $nilai->filter(fn ($v) => $v === 'terlambat')->count();
        $dinilai = $tepat + $terlambat;

        return [
            'tepat' => $tepat,
            'terlambat' => $terlambat,
            'lewat' => $nilai->filter(fn ($v) => $v === 'lewat')->count(),   // belum selesai & deadline lewat
            // Persentase HANYA dari kartu yang sudah selesai & punya deadline.
            // Kartu yang masih berjalan belum bisa dinilai tepat atau tidak —
            // memasukkannya membuat angka ketepatan turun hanya karena masih
            // banyak pekerjaan berlangsung.
            'persen_tepat' => $dinilai > 0 ? round($tepat / $dinilai * 100, 1) : null,
        ];
    }

    /** Penjumlahan lintas board untuk kartu ringkasan di puncak halaman. */
    private static function totalLintasBoard(array $board): array
    {
        $sum = fn (string $key) => array_sum(array_column($board, $key));
        $tepat = array_sum(array_map(fn ($b) => $b['ketepatan']['tepat'], $board));
        $terlambat = array_sum(array_map(fn ($b) => $b['ketepatan']['terlambat'], $board));
        $target = $sum('target');

        return [
            'total' => $sum('total'),
            'done' => $sum('done'),
            'target' => $target,
            'percent' => $target > 0 ? round($sum('done') / $target * 100, 1) : null,
            'tepat' => $tepat,
            'terlambat' => $terlambat,
            'lewat' => array_sum(array_map(fn ($b) => $b['ketepatan']['lewat'], $board)),
            'persen_tepat' => $tepat + $terlambat > 0 ? round($tepat / ($tepat + $terlambat) * 100, 1) : null,
        ];
    }

    /** Tetapkan/ubah target jumlah kartu selesai satu board pada satu kuartal. */
    public function storeTarget(Request $request)
    {
        $data = $request->validate([
            'board_key' => ['required', Rule::exists('categories', 'key')->where('type', 'kanban')],
            'year' => 'required|integer|min:2000|max:2100',
            'quarter' => 'required|integer|min:1|max:4',
            'target_done' => 'required|integer|min:0',
            'note' => 'nullable|string|max:1000',
        ]);

        // updateOrCreate, bukan create: kunci uniknya (board, tahun, kuartal)
        // dijaga skema, jadi create ulang melempar QueryException saat target
        // sekadar dikoreksi — yang justru kejadian paling sering.
        BoardQuarterTarget::updateOrCreate(
            $request->only(['board_key', 'year', 'quarter']),
            ['target_done' => $data['target_done'], 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id],
        );

        return back()->with('status', 'Target board disimpan.');
    }
}
