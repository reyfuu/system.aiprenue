<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\BoardQuarterTarget;
use App\Models\Category;
use App\Models\KeyResult;
use App\Models\Label;
use App\Models\Objective;
use App\Models\OkrPeriod;
use App\Models\Pipeline;
use App\Models\User;
use App\Notifications\OkrAssignmentNotification;
use App\Support\OkrMetrics;
use App\Support\OkrNotifications;
use App\Support\Quarter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * OKR tingkat perusahaan per kuartal: Objective berisi Key Result.
 *
 *  Realisasi KR bertipe `auto` DIHITUNG dari modul Insight & Pembukuan
 *  (lihat OkrMetrics) — tak ada angka realisasi otomatis yang diketik manusia
 *  di sini, jadi ia tak bisa basi saat data sumbernya dikoreksi. KR `manual`
 *  ada untuk target yang memang tak punya sumber data.
 *
 *  Halaman ini TERKUNCI untuk owner, manager, dan IT super admin lewat
 *  User::canSee(), sejajar dgn pembukuan & tracking: isinya omset dan
 *  pertumbuhan audiens. KPI board dan rapor per orang ada di KpiController —
 *  audiensnya lebih luas.
 *
 *  Kuartal dipilih lewat ?q=YYYY-Qn; tanpa itu memakai kuartal berjalan.
 */
class OkrController extends Controller
{
    /** Berapa kuartal ke belakang yang ditarik untuk grafik tren. */
    private const TREN_KUARTAL = 6;

    /** Penanda kerja yang diminta untuk OKR. Definisinya tetap berasal dari
     *  tabel labels supaya nama dan warna sama dengan kartu Kanban. */
    private const PRIORITY_NAMES = ['Urgent', 'Penting'];

    public function index(Request $request)
    {
        // ?q ngawur diabaikan (jatuh ke kuartal berjalan), bukan bikin 4xx —
        // sikap yang sama dgn filter jenis di PipelineController.
        $q = Quarter::parse($request->query('q')) ?? Quarter::current();
        [$year, $quarter] = [$q['year'], $q['quarter']];
        [$start, $end] = Quarter::range($year, $quarter);

        // Dihitung SEKALI untuk seluruh halaman lalu dioper ke tiap KR —
        // kalau tiap KR memanggilnya sendiri, satu halaman bisa menembak
        // belasan rangkaian query untuk angka yang sama persis.
        $realisasi = OkrMetrics::realisasi($year, $quarter);

        $daftar = Objective::forQuarter($year, $quarter);
        $kuartalLalu = $this->kuartalSebelum($year, $quarter);

        // Kartu tautan untuk seluruh KR bersumber 'kartu' di halaman ini,
        // diambil SEKALI. Tanpa ini tiap KR menembak query sendiri untuk
        // menghitung & mendaftar kartunya (N+1). Dikelompokkan per KR;
        // hitungan selesai disuntikkan ke model lewat 'kartu_selesai' supaya
        // KeyResult::actual() tak query ulang (lihat model).
        $krKartuIds = $daftar->flatMap->keyResults->pluck('id');
        $kartuPerKr = Pipeline::whereIn('key_result_id', $krKartuIds)
            ->with('assignee:id,name')
            ->orderBy('position')->orderBy('id')
            // is_kr_master WAJIB ikut terpilih: Vue mencari card utama lewat
            // flag ini; tanpanya semua kartu terbaca bukan master.
            ->get(['id', 'key_result_id', 'category', 'assigned_to', 'endorse', 'progress', 'deadline', 'completed_at', 'is_kr_master'])
            ->groupBy('key_result_id');
        $boardKeys = $daftar->flatMap->keyResults->where('source', 'kartu')
            ->pluck('board_key')->filter()->unique()->values();
        $selesaiPerBoard = Pipeline::whereIn('category', $boardKeys)
            ->whereBetween('deadline', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('completed_at')
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')->pluck('total', 'category');
        $targetPerBoard = BoardQuarterTarget::whereIn('board_key', $boardKeys)
            ->where('year', $year)->where('quarter', $quarter)
            ->pluck('target_done', 'board_key');
        $namaBoard = Category::whereIn('key', $boardKeys)->pluck('name', 'key');
        // Suntikkan angka board sebelum Objective::progress() dipanggil, supaya
        // progress Objective dan angka KR memakai sumber yang persis sama.
        foreach ($daftar->flatMap->keyResults->where('source', 'kartu') as $kr) {
            if ($kr->board_key) {
                $kr->setAttribute('kartu_selesai', (int) ($selesaiPerBoard[$kr->board_key] ?? 0));
                $kr->target = (string) ($targetPerBoard[$kr->board_key] ?? 0);
            }
        }

        $objectives = $daftar->map(function (Objective $o) use ($realisasi, $kartuPerKr, $namaBoard) {
            $omsetTarget = (float) ($o->omset_target ?? 0);
            $omsetActual = (float) ($realisasi['omset'] ?? 0);

            return [
                'id' => $o->id,
                'title' => $o->title,
                'description' => $o->description,
                'priority' => $o->priority,
                // Target omzet hidup di Objective, bukan lagi menjadi satu KR
                // semu. Realisasi tetap dihitung dari Pembukuan kuartal aktif.
                'omset_target' => $omsetTarget,
                'omset_actual' => $omsetActual,
                'omset_percent' => $omsetTarget > 0
                    ? round($omsetActual / $omsetTarget * 100, 1)
                    : null,
                // id ikut dikirim (bukan cuma nama) supaya form edit bisa
                // mengisi pilihan PIC yang tersimpan.
                'omset_owner_id' => $o->omset_owner_id,
                'omset_owner_name' => $o->omsetOwner?->name,
                'progress' => $o->progress($realisasi),
                'created_by_name' => $o->creator?->name,
                'key_results' => $o->keyResults->map(function (KeyResult $kr) use ($realisasi, $kartuPerKr, $namaBoard) {
                    $kartu = $kartuPerKr->get($kr->id, collect());
                    if ($kr->source === 'kartu' && ! $kr->board_key) {
                        $kr->setAttribute('kartu_selesai', $kartu->whereNotNull('completed_at')->count());
                    }

                    return [
                        'id' => $kr->id,
                        'title' => $kr->title,
                        'description' => $kr->description,
                        'source' => $kr->source,
                        'source_label' => $kr->source === 'auto'
                            ? (OkrMetrics::METRICS[$kr->metric] ?? 'Otomatis')
                            : (KeyResult::SOURCES[$kr->source] ?? $kr->source),
                        'board_key' => $kr->board_key,
                        'board_name' => $kr->board_key ? ($namaBoard[$kr->board_key] ?? $kr->board_key) : null,
                        'metric' => $kr->metric,
                        'unit' => $kr->unit,
                        'priority' => $kr->priority,
                        'target' => (float) $kr->target,
                        'actual' => $kr->actual($realisasi),
                        'percent' => $kr->percent($realisasi),
                        'owner_name' => $kr->owner?->name,
                        // Tugas eksekusi dapat ditautkan ke semua jenis KR; sumber
                        // angka dan daftar pekerjaan adalah dua hal berbeda.
                        'kartu' => $kartu->map(fn (Pipeline $p) => [
                            'id' => $p->id,
                            'judul' => $p->endorse,
                            'description' => $p->description,
                            'board' => $p->category,
                            'is_master' => (bool) $p->is_kr_master,
                            'pic' => $p->assignee?->name,
                            // id & tanggal mentah untuk mengisi form edit KR;
                            // 'pic' di atas hanya cukup untuk ditampilkan.
                            'assigned_to' => $p->assigned_to,
                            'deadline' => $p->deadline?->toDateString(),
                            'progress' => $p->progress,
                            'selesai' => $p->completed_at !== null,
                            'ketepatan' => $p->ketepatan(),
                        ])->values(),
                    ];
                })->values(),
            ];
        })->values();

        $kanbanBoards = Category::where('type', 'kanban')->orderBy('name')->get(['key', 'name']);
        $kanbanColumns = BoardColumn::whereIn('board_key', $kanbanBoards->pluck('key'))
            ->orderBy('position')
            ->get(['board_key', 'key', 'name'])
            ->groupBy('board_key')
            ->map(fn ($columns) => $columns->values())
            ->all();

        return Inertia::render('Okr', [
            'quarter' => ['year' => $year, 'quarter' => $quarter, 'key' => $year.'-Q'.$quarter, 'label' => Quarter::label($year, $quarter)],
            // Judul OKR perusahaan bisa diedit bebas per kuartal; kalau belum
            // pernah diedit (baris kosong), pakai judul default.
            'okrTitle' => OkrPeriod::where('year', $year)->where('quarter', $quarter)->value('title')
                ?? 'OKR Perusahaan '.Quarter::label($year, $quarter),
            'quarterOptions' => Quarter::options(),
            'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'objectives' => $objectives,
            'ringkasan' => $this->ringkasan($objectives, $realisasi),
            'tren' => $this->tren($year, $quarter),
            // Omzet sekarang target Objective. Pilihan KR otomatis hanya untuk
            // metrik yang memang tetap menjadi hasil pendukung.
            'metrics' => collect(OkrMetrics::METRICS)->except('omset')->all(),
            'sources' => KeyResult::SOURCES,
            'priorities' => Label::where('group', 2)
                ->whereIn('name', self::PRIORITY_NAMES)
                ->get(['name', 'color'])
                ->sortBy(fn (Label $label) => array_search($label->name, self::PRIORITY_NAMES, true))
                ->values(),
            'kanbanBoards' => $kanbanBoards,
            'kanbanColumns' => $kanbanColumns,
            'cardCategories' => Label::orderBy('group')->orderBy('id')->get(['name', 'group', 'color']),
            'staff' => User::orderBy('name')->get(['id', 'name', 'role']),
            'units' => KeyResult::UNITS,
            // Kartu todolist yang BELUM tertaut ke KR mana pun — pilihan untuk
            // "tautkan kartu yang sudah ada". Penautan dikelola dari halaman ini,
            // bukan dari Kanban (Kanban murni delegasi). Diambil sekali di sini.
            'kartuTersedia' => $request->user()->canManage() ? Pipeline::where('category', 'todolist')
                ->whereNull('key_result_id')->whereNull('archived_at')
                ->orderByDesc('id')->limit(100)->get(['id', 'endorse'])
                ->map(fn ($p) => ['id' => $p->id, 'judul' => $p->endorse])->values() : [],
            'canManage' => $request->user()->canManage(),
            // Tawaran salin hanya muncul saat kuartal ini MASIH KOSONG dan
            // kuartal sebelumnya ada isinya. Menawarkannya pada kuartal yang
            // sudah terisi mengundang duplikat: dua Objective serupa yang
            // targetnya berbeda, tanpa cara tahu mana yang berlaku.
            'bisaSalin' => $daftar->isEmpty()
                && Objective::where('year', $kuartalLalu['year'])->where('quarter', $kuartalLalu['quarter'])->exists(),
            'kuartalLaluLabel' => Quarter::label($kuartalLalu['year'], $kuartalLalu['quarter']),
        ]);
    }

    /**
     * Ubah judul OKR perusahaan untuk kuartal yang sedang dibuka (bebas per
     * kuartal). Kuartal diambil dari ?q (sama seperti index); kalau kosong →
     * kuartal berjalan. Upsert: buat baris bila belum ada. Izin lewat middleware.
     */
    public function updateTitle(Request $request)
    {
        $q = Quarter::parse($request->input('q')) ?? Quarter::current();
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        OkrPeriod::updateOrCreate(
            ['year' => $q['year'], 'quarter' => $q['quarter']],
            ['title' => trim($data['title'])],
        );

        return back();
    }

    /** Kuartal sebelum yang diberikan, ikut mundur tahun saat menyeberang Q1. */
    private function kuartalSebelum(int $year, int $quarter): array
    {
        return $quarter === 1
            ? ['year' => $year - 1, 'quarter' => 4]
            : ['year' => $year, 'quarter' => $quarter - 1];
    }

    /**
     * Salin Objective + Key Result kuartal sebelumnya ke kuartal yang dipilih.
     *
     *  Yang disalin hanya STRUKTUR & TARGET. Realisasi manual (`actual_manual`)
     *  sengaja tidak ikut — itu pencapaian periode lalu, dan membawanya serta
     *  membuat kuartal baru lahir dengan progress yang bukan miliknya.
     *
     *  Ditolak bila kuartal tujuan sudah berisi. Menyalin ke kuartal yang sudah
     *  terisi menghasilkan Objective kembar bertarget berbeda tanpa cara tahu
     *  mana yang berlaku. Tombolnya memang disembunyikan di UI, tapi request
     *  langsung harus ikut ditolak.
     */
    public function salinKuartalLalu(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'quarter' => 'required|integer|min:1|max:4',
        ]);

        [$year, $quarter] = [(int) $data['year'], (int) $data['quarter']];

        if (Objective::where('year', $year)->where('quarter', $quarter)->exists()) {
            throw ValidationException::withMessages([
                'quarter' => 'Kuartal ini sudah punya Objective. Salin hanya bisa ke kuartal yang masih kosong.',
            ]);
        }

        $lalu = $this->kuartalSebelum($year, $quarter);
        $sumber = Objective::forQuarter($lalu['year'], $lalu['quarter']);

        if ($sumber->isEmpty()) {
            throw ValidationException::withMessages([
                'quarter' => 'Kuartal sebelumnya belum punya Objective untuk disalin.',
            ]);
        }

        // Transaksi: separuh tersalin = kuartal berisi Objective tanpa Key
        // Result, dan tombol salin sudah telanjur hilang karena kuartalnya
        // tak lagi kosong.
        DB::transaction(function () use ($sumber, $year, $quarter, $request) {
            foreach ($sumber as $o) {
                $baru = Objective::create([
                    'year' => $year,
                    'quarter' => $quarter,
                    'title' => $o->title,
                    'description' => $o->description,
                    'priority' => $o->priority,
                    'omset_target' => $o->omset_target,
                    'omset_owner_id' => $o->omset_owner_id,
                    'position' => $o->position,
                    'created_by' => $request->user()->id,
                ]);

                foreach ($o->keyResults as $kr) {
                    KeyResult::create([
                        'objective_id' => $baru->id,
                        'title' => $kr->title,
                        'source' => $kr->source,
                        'metric' => $kr->metric,
                        'target' => $kr->target,
                        'actual_manual' => null,     // realisasi TIDAK ikut disalin
                        'unit' => $kr->unit,
                        'position' => $kr->position,
                        'owner_id' => $kr->owner_id,
                        'created_by' => $request->user()->id,
                    ]);
                }
            }
        });

        return back()->with('status', 'Objective & target kuartal lalu disalin. Tinjau targetnya sebelum dipakai.');
    }

    /** Angka puncak halaman. Objective/KR tanpa target tak ikut dihitung —
     *  alasan yang sama dgn Objective::progress(). */
    private function ringkasan($objectives, array $realisasi): array
    {
        $krs = $objectives->pluck('key_results')->flatten(1);
        $persenObjective = $objectives->pluck('progress')->filter(fn ($p) => $p !== null);
        $persenKr = $krs->pluck('percent')->filter(fn ($p) => $p !== null);

        $omsetTarget = $objectives->sum(fn ($o) => (float) ($o->omset_target ?? 0));
        $omsetActual = (float) ($realisasi['omset'] ?? 0);

        return [
            'objectives' => $objectives->count(),
            'key_results' => $krs->count(),
            'progress' => $persenObjective->isEmpty() ? null : round($persenObjective->avg(), 1),
            'tercapai' => $persenKr->filter(fn ($p) => $p >= 100)->count(),
            'tertinggal' => $persenKr->filter(fn ($p) => $p < 60)->count(),
            'omset_target' => $omsetTarget,
            'omset_actual' => $omsetActual,
            'omset_percent' => $omsetTarget > 0 ? round($omsetActual / $omsetTarget * 100, 1) : null,
        ];
    }

    /**
     * Tren tiap metrik otomatis selama beberapa kuartal terakhir.
     *
     * Target view/subscriber berasal dari KR otomatis. Target omzet berasal dari
     * Objective dan dijumlahkan bila satu kuartal memiliki beberapa Objective
     * bertarget omzet.
     */
    private function tren(int $year, int $quarter): array
    {
        $periode = [];
        for ($i = self::TREN_KUARTAL - 1; $i >= 0; $i--) {
            $y = $year;
            $qq = $quarter - $i;
            while ($qq <= 0) {
                $qq += 4;
                $y--;
            }
            $periode[] = ['year' => $y, 'quarter' => $qq];
        }

        $target = KeyResult::query()
            ->join('objectives', 'objectives.id', '=', 'key_results.objective_id')
            ->where('key_results.source', 'auto')
            ->whereNotNull('key_results.metric')
            ->where('key_results.metric', '!=', 'omset')
            ->selectRaw('objectives.year, objectives.quarter, key_results.metric, SUM(key_results.target) as total')
            ->groupBy('objectives.year', 'objectives.quarter', 'key_results.metric')
            ->get()
            ->keyBy(fn ($r) => $r->year.'-'.$r->quarter.'-'.$r->metric);
        $targetOmset = Objective::query()
            ->whereNotNull('omset_target')
            ->selectRaw('year, quarter, SUM(omset_target) as total')
            ->groupBy('year', 'quarter')
            ->get()
            ->keyBy(fn ($r) => $r->year.'-'.$r->quarter);

        $out = [];
        foreach (OkrMetrics::METRICS as $metric => $label) {
            $titik = [];
            foreach ($periode as $p) {
                $key = $p['year'].'-'.$p['quarter'];
                $t = $metric === 'omset'
                    ? (float) ($targetOmset[$key]->total ?? 0)
                    : (float) ($target[$key.'-'.$metric]->total ?? 0);
                $a = (float) (OkrMetrics::realisasi($p['year'], $p['quarter'])[$metric] ?? 0);
                $titik[] = [
                    'label' => Quarter::label($p['year'], $p['quarter']),
                    'target' => $t,
                    'actual' => $a,
                    'percent' => $t > 0 ? round($a / $t * 100, 1) : null,
                ];
            }
            $out[] = ['metric' => $metric, 'label' => $label, 'unit' => OkrMetrics::UNITS[$metric] ?? 'angka', 'points' => $titik];
        }

        return $out;
    }

    // ---------------------------------------------------------- Objective

    public function storeObjective(Request $request)
    {
        $data = $this->validasiObjective($request, true);
        $omsetTarget = (float) ($data['omset_target'] ?? 0);
        $omsetOwnerId = isset($data['omset_owner_id'])
            ? (int) $data['omset_owner_id']
            : User::where('role', 'owner')->orderBy('id')->value('id');
        $data['omset_target'] = $omsetTarget > 0 ? $omsetTarget : null;
        $data['omset_owner_id'] = $omsetTarget > 0 ? $omsetOwnerId : null;

        $data['created_by'] = $request->user()->id;
        // Objective baru masuk paling bawah, bukan paling atas: urutan yang
        // sudah disusun pemakai tak boleh bergeser tiap kali ia menambah satu.
        $data['position'] = (int) Objective::where('year', $data['year'])
            ->where('quarter', $data['quarter'])->max('position') + 1;

        // Objective dan notifikasinya harus utuh dalam satu transaksi.
        DB::transaction(function () use ($data, $omsetTarget, $omsetOwnerId, $request): void {
            $objective = Objective::create($data);

            if ($omsetTarget <= 0) {
                return;
            }

            // Staff tidak dapat membuka halaman OKR yang memuat angka perusahaan,
            // tetapi tetap perlu tahu target yang menjadi tanggung jawabnya.
            // Karena itu detail penting masuk ke notifikasi server tanpa tautan
            // ke /okr yang akan berakhir 403 untuk role staff.
            // kirim() melewatkan bila PIC-nya adalah pembuat Objective sendiri.
            OkrNotifications::kirim(
                User::find($omsetOwnerId),
                $request->user()->id,
                new OkrAssignmentNotification(
                    title: 'Target omzet baru',
                    message: sprintf(
                        'Anda ditetapkan sebagai PIC target omzet Rp %s untuk “%s” (%s).',
                        number_format($omsetTarget, 0, ',', '.'),
                        $objective->title,
                        Quarter::label($objective->year, $objective->quarter),
                    ),
                    url: null,
                    objectiveId: $objective->id,
                    keyResultId: null,
                    priority: $objective->priority,
                )
            );
        });

        return back()->with(
            'status',
            $omsetTarget > 0
                ? 'Objective dan target omzet ditambahkan.'
                : 'Objective ditambahkan.'
        );
    }

    public function updateObjective(Request $request, Objective $objective)
    {
        // Field omzet divalidasi HANYA bila ikut dikirim. Klien lama yang belum
        // mengenalnya tak boleh diam-diam menghapus target yang sudah ada
        // hanya karena field-nya absen dari request.
        $denganOmset = $request->has('omset_target') || $request->has('omset_owner_id');
        $data = $this->validasiObjective($request, $denganOmset);

        if ($denganOmset) {
            $omsetTarget = (float) ($data['omset_target'] ?? 0);
            $data['omset_target'] = $omsetTarget > 0 ? $omsetTarget : null;
            $data['omset_owner_id'] = $omsetTarget > 0
                ? (isset($data['omset_owner_id'])
                    ? (int) $data['omset_owner_id']
                    : User::where('role', 'owner')->orderBy('id')->value('id'))
                : null;
        }

        // Nilai lama dicatat SEBELUM update — pembanding untuk memutuskan
        // notifikasi perubahan apa (bila ada) yang layak dikirim.
        $lama = [
            'target' => (float) ($objective->omset_target ?? 0),
            'pic' => $objective->omset_owner_id,
        ];

        $objective->update($data);

        if ($denganOmset) {
            $this->notifikasiPerubahanOmset($objective, $lama, $request->user()->id);
        }

        return back()->with('status', 'Objective diperbarui.');
    }

    /**
     * Kabari PIC bila penugasan/target omzet berubah lewat edit Objective.
     *
     *  Tiga kejadian yang layak dikabari: PIC diganti (PIC lama diberi tahu
     *  dialihkan/dicabut, PIC baru menerima penugasan), atau PIC tetap tetapi
     *  angka targetnya berubah. Tak ada perubahan = tak ada notifikasi.
     */
    private function notifikasiPerubahanOmset(Objective $objective, array $lama, int $pelakuId): void
    {
        $target = (float) ($objective->omset_target ?? 0);
        $picBaru = $objective->omset_owner_id;

        if ($target === $lama['target'] && (int) $picBaru === (int) $lama['pic']) {
            return;
        }

        if ((int) $picBaru !== (int) $lama['pic']) {
            // PIC lama: penugasannya dialihkan ke orang lain, atau dicabut
            // sama sekali bila target omzet ikut dihapus.
            OkrNotifications::kirim(
                User::find($lama['pic']),
                $pelakuId,
                new OkrAssignmentNotification(
                    title: 'Penugasan omzet berubah',
                    message: $picBaru
                        ? sprintf(
                            'Penugasan target omzet “%s” dialihkan ke %s.',
                            $objective->title,
                            User::find($picBaru)?->name ?? 'orang lain',
                        )
                        : sprintf('Penugasan target omzet “%s” dicabut.', $objective->title),
                    url: null,
                    objectiveId: $objective->id,
                    keyResultId: null,
                    priority: $objective->priority,
                    kind: 'okr_perubahan',
                )
            );

            // PIC baru menerima kabar penugasan dengan isi yang sama seperti
            // saat Objective dibuat — baginya ini memang penugasan baru.
            if ($picBaru) {
                OkrNotifications::kirim(
                    User::find($picBaru),
                    $pelakuId,
                    new OkrAssignmentNotification(
                        title: 'Target omzet baru',
                        message: sprintf(
                            'Anda ditetapkan sebagai PIC target omzet Rp %s untuk “%s” (%s).',
                            number_format($target, 0, ',', '.'),
                            $objective->title,
                            Quarter::label($objective->year, $objective->quarter),
                        ),
                        url: null,
                        objectiveId: $objective->id,
                        keyResultId: null,
                        priority: $objective->priority,
                    )
                );
            }

            return;
        }

        // PIC tetap, angkanya yang berubah.
        OkrNotifications::kirim(
            User::find($picBaru),
            $pelakuId,
            new OkrAssignmentNotification(
                title: 'Target omzet berubah',
                message: sprintf(
                    'Target omzet “%s” diubah menjadi Rp %s.',
                    $objective->title,
                    number_format($target, 0, ',', '.'),
                ),
                url: null,
                objectiveId: $objective->id,
                keyResultId: null,
                priority: $objective->priority,
                kind: 'okr_perubahan',
            )
        );
    }

    /** Key Result ikut terhapus lewat cascadeOnDelete di skema — tanpa
     *  Objective ia tak punya arti. */
    public function destroyObjective(Objective $objective)
    {
        $objective->delete();

        return back()->with('status', 'Objective dihapus.');
    }

    private function validasiObjective(Request $request, bool $denganOmset = false): array
    {
        $rules = [
            'year' => 'required|integer|min:2000|max:2100',
            'quarter' => 'required|integer|min:1|max:4',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'priority_name' => 'nullable|string|max:50',
        ];
        if ($denganOmset) {
            // Nol/kosong berarti Objective tanpa target omzet. Nilai positif
            // disimpan langsung pada Objective.
            $rules['omset_target'] = 'nullable|numeric|min:0';
            $rules['omset_owner_id'] = 'nullable|exists:users,id';
        }

        $data = $request->validate($rules);

        return $this->denganPrioritas($data);
    }

    // --------------------------------------------------------- Key Result

    public function storeKeyResult(Request $request)
    {
        $execution = $request->validate([
            'kanban_board_key' => ['nullable', Rule::exists('categories', 'key')->where('type', 'kanban')],
            'kanban_column_key' => 'nullable|required_with:kanban_board_key|string|max:100',
            'card_category' => 'nullable|string|max:100',
            'card_description' => 'nullable|string|max:5000',
            'assigned_to' => 'nullable|exists:users,id',
            'deadline' => 'nullable|date',
        ]);
        $column = null;
        if (! empty($execution['kanban_board_key'])) {
            $column = BoardColumn::where('board_key', $execution['kanban_board_key'])
                ->where('key', $execution['kanban_column_key'] ?? '')
                ->first();
            if (! $column) {
                throw ValidationException::withMessages([
                    'kanban_column_key' => 'Kolom tidak tersedia pada board yang dipilih.',
                ]);
            }
        }
        $label = null;
        if (! empty($execution['card_category'])) {
            $label = Label::where('name', $execution['card_category'])->first();
            if (! $label) {
                throw ValidationException::withMessages([
                    'card_category' => 'Kategori card tidak tersedia.',
                ]);
            }
        }
        $data = $this->validasiKeyResult($request);
        if ($data['source'] === 'auto' && $data['metric'] === 'omset') {
            throw ValidationException::withMessages([
                'metric' => 'Target omzet disimpan pada Objective.',
            ]);
        }
        $data['created_by'] = $request->user()->id;
        // Bila ada card eksekusi, PIC card juga menjadi penanggung jawab KR.
        // Tanpa card/PIC, perilaku lama tetap dipertahankan: owner pertama.
        $data['owner_id'] = $execution['assigned_to']
            ?? User::where('role', 'owner')->orderBy('id')->value('id');
        $data['position'] = (int) KeyResult::where('objective_id', $data['objective_id'])->max('position') + 1;

        // KR, card eksekusi, dan notifikasi adalah satu paket. Bila salah
        // satunya gagal, transaksi membatalkan semuanya agar staff tidak
        // menerima notifikasi untuk pekerjaan yang sebenarnya tidak tersimpan.
        DB::transaction(function () use ($data, $execution, $column, $label, $request): void {
            $keyResult = KeyResult::create($data);

            $card = null;
            if (! empty($execution['kanban_board_key'])) {
                $card = Pipeline::create([
                    'category' => $execution['kanban_board_key'],
                    'account' => 'fk',
                    'payment_status' => 'belum',
                    'progress' => $column->key,
                    'endorse' => $keyResult->title,
                    'description' => $execution['card_description'] ?? null,
                    'labels' => $label ? [['name' => $label->name, 'group' => $label->group, 'color' => $label->color]] : [],
                    'assigned_to' => $execution['assigned_to'] ?? null,
                    'deadline' => $execution['deadline'] ?? null,
                    'key_result_id' => $keyResult->id,
                    'is_kr_master' => true,
                    'created_by' => $data['created_by'],
                ]);
            }

            // Penanggung jawab KR diberi tahu ADA atau TIDAK adanya card
            // eksekusi — tanpa card pun penugasan KR tetap nyata. Tautan ke
            // Kanban hanya disertakan bila card-nya ada.
            OkrNotifications::kirim(
                User::find($execution['assigned_to'] ?? $data['owner_id']),
                $request->user()->id,
                new OkrAssignmentNotification(
                    title: $card ? 'Pekerjaan OKR baru' : 'Penanggung jawab KR baru',
                    message: $card
                        ? sprintf(
                            'Anda ditugaskan pada “%s” untuk Objective “%s”.',
                            $keyResult->title,
                            $keyResult->objective()->value('title'),
                        )
                        : sprintf(
                            'Anda menjadi penanggung jawab KR “%s” pada Objective “%s”.',
                            $keyResult->title,
                            $keyResult->objective()->value('title'),
                        ),
                    url: $card ? route('pipelines.kanban', [
                        'category' => $card->category,
                        'card' => $card->id,
                    ]) : null,
                    objectiveId: $keyResult->objective_id,
                    keyResultId: $keyResult->id,
                    priority: $keyResult->priority,
                    pipelineId: $card?->id,
                )
            );
        });

        return back()->with('status', 'Key Result ditambahkan.');
    }

    public function updateKeyResult(Request $request, KeyResult $keyResult)
    {
        $data = $this->validasiKeyResult($request, $keyResult);
        unset($data['objective_id']);   // KR tak berpindah induk lewat form ini

        // PIC & deadline card utama ikut bisa dikoreksi dari form edit KR.
        // Keduanya milik card eksekusi, bukan kolom KR, jadi divalidasi
        // terpisah dari validasiKeyResult().
        $execution = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'deadline' => 'nullable|date',
        ]);

        $master = $keyResult->cards()->where('is_kr_master', true)->first();
        // Nilai lama dicatat SEBELUM update sebagai pembanding notifikasi.
        $picLama = $master?->assigned_to;
        $deadlineLama = $master?->deadline?->toDateString();

        // KR dan card utamanya satu paket — keduanya tersimpan atau batal
        // sama sekali, sama seperti saat pembuatan.
        DB::transaction(function () use ($keyResult, $data, $master, $execution): void {
            $keyResult->update($data);

            if (! $master) {
                return;
            }

            $master->update([
                'assigned_to' => $execution['assigned_to'] ?? null,
                'deadline' => $execution['deadline'] ?? null,
                // Deskripsi tugas ikut deskripsi KR — satu field yang diedit
                // user di modal, ditampilkan di kartu tugas OKR.
                'description' => $data['description'] ?? null,
            ]);

            // Penanggung jawab KR mengikuti PIC card utama — invariant yang
            // sama dengan saat KR dibuat. Hanya bila PIC diisi: mengosongkan
            // PIC tak boleh menghapus pemilik KR yang sudah tercatat.
            if (! empty($execution['assigned_to'])) {
                $keyResult->update(['owner_id' => $execution['assigned_to']]);
            }
        });

        if ($master) {
            $this->notifikasiPerubahanEksekusi(
                $keyResult,
                $master->fresh(),
                $picLama,
                $deadlineLama,
                $request->user()->id,
            );
        }

        return back()->with('status', 'Key Result diperbarui.');
    }

    /**
     * Kabari PIC lama/baru bila penugasan atau deadline card utama berubah.
     *
     *  PIC diganti → PIC lama diberi tahu penugasannya dialihkan, PIC baru
     *  menerima notifikasi penugasan (isinya sama seperti saat KR dibuat).
     *  PIC tetap tetapi deadline berubah → PIC diberi tahu tanggal barunya.
     *  Tak ada perubahan = tak ada notifikasi.
     */
    private function notifikasiPerubahanEksekusi(
        KeyResult $keyResult,
        Pipeline $card,
        ?int $picLama,
        ?string $deadlineLama,
        int $pelakuId,
    ): void {
        $picBaru = $card->assigned_to;
        $deadlineBaru = $card->deadline?->toDateString();

        if ((int) $picBaru === (int) $picLama && $deadlineBaru === $deadlineLama) {
            return;
        }

        $url = route('pipelines.kanban', [
            'category' => $card->category,
            'card' => $card->id,
        ]);

        if ((int) $picBaru !== (int) $picLama) {
            // PIC lama: tanpa tautan — card itu bukan lagi tanggung jawabnya.
            OkrNotifications::kirim(
                User::find($picLama),
                $pelakuId,
                new OkrAssignmentNotification(
                    title: 'Penugasan OKR dialihkan',
                    message: sprintf(
                        'Penugasan “%s” dialihkan ke %s.',
                        $keyResult->title,
                        $card->assignee?->name ?? 'orang lain',
                    ),
                    url: null,
                    objectiveId: $keyResult->objective_id,
                    keyResultId: $keyResult->id,
                    priority: $keyResult->priority,
                    kind: 'okr_perubahan',
                    pipelineId: $card->id,
                )
            );

            // PIC baru: baginya ini penugasan baru, lengkap dgn tautan card.
            OkrNotifications::kirim(
                $card->assignee,
                $pelakuId,
                new OkrAssignmentNotification(
                    title: 'Pekerjaan OKR baru',
                    message: sprintf(
                        'Anda ditugaskan pada “%s” untuk Objective “%s”.',
                        $keyResult->title,
                        $keyResult->objective()->value('title'),
                    ),
                    url: $url,
                    objectiveId: $keyResult->objective_id,
                    keyResultId: $keyResult->id,
                    priority: $keyResult->priority,
                    pipelineId: $card->id,
                )
            );

            return;
        }

        // PIC tetap, deadlinenya yang berubah.
        OkrNotifications::kirim(
            $card->assignee,
            $pelakuId,
            new OkrAssignmentNotification(
                title: 'Deadline OKR berubah',
                message: sprintf(
                    'Deadline “%s” diubah menjadi %s.',
                    $keyResult->title,
                    $deadlineBaru ?? 'tanpa tanggal',
                ),
                url: $url,
                objectiveId: $keyResult->objective_id,
                keyResultId: $keyResult->id,
                priority: $keyResult->priority,
                kind: 'okr_perubahan',
                pipelineId: $card->id,
            )
        );
    }

    public function destroyKeyResult(KeyResult $keyResult)
    {
        $keyResult->delete();

        return back()->with('status', 'Key Result dihapus.');
    }

    // ------------------------------------------------- kartu (langkah) KR
    //
    //  Endpoint lama di bawah melayani langkah tambahan pada board `todolist`.
    //  Card utama KR dibuat oleh storeKeyResult() pada board yang dipilih di
    //  form. Semua endpoint ini hanya untuk KR bersumber `kartu`; menautkan
    //  langkah hitungan ke KR auto/manual tidak memiliki arti.

    /** Buat kartu todolist baru langsung sbg langkah menuju sebuah KR. */
    public function storeKartu(Request $request, KeyResult $keyResult)
    {
        $this->pastikanKrKartu($keyResult);
        $data = $request->validate([
            'endorse' => 'required|string|max:255',
            'deadline' => 'nullable|date',
        ]);

        // Kolom pertama board todolist = tahap awal. Diambil dinamis (bukan
        // hardcode 'todo') supaya ikut bila kolomnya pernah diubah.
        $kolomAwal = BoardColumn::where('board_key', 'todolist')->orderBy('position')->value('key') ?? 'todo';

        Pipeline::create([
            'category' => 'todolist',
            'account' => 'fk',
            'payment_status' => 'belum',
            'progress' => $kolomAwal,
            'endorse' => $data['endorse'],
            'deadline' => $data['deadline'] ?? null,
            'key_result_id' => $keyResult->id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Langkah ditambahkan ke Kanban todolist.');
    }

    /** Tautkan kartu todolist yang SUDAH ADA ke sebuah KR. */
    public function attachKartu(Request $request, KeyResult $keyResult)
    {
        $this->pastikanKrKartu($keyResult);
        $data = $request->validate([
            // Hanya kartu board todolist yang boleh dituju — sama dgn keputusan
            // "todolist saja". exists+where menegakkannya di DB, bukan cuma di UI.
            'pipeline_id' => ['required', Rule::exists('pipelines', 'id')->where('category', 'todolist')],
        ]);

        Pipeline::where('id', $data['pipeline_id'])->update(['key_result_id' => $keyResult->id]);

        return back()->with('status', 'Kartu ditautkan ke goal.');
    }

    /** Lepas tautan sebuah kartu dari KR (kartu tetap hidup di papannya). */
    public function detachKartu(KeyResult $keyResult, Pipeline $pipeline)
    {
        // Hanya melepas bila kartu memang tertaut ke KR ini — cegah melepas
        // kartu milik KR lain lewat id yang ditebak.
        if ($pipeline->key_result_id === $keyResult->id) {
            $pipeline->update(['key_result_id' => null]);
        }

        return back()->with('status', 'Tautan dilepas.');
    }

    /** KR harus bersumber 'kartu'. Menautkan langkah ke KR auto/manual tak
     *  punya arti — realisasinya tak dihitung dari kartu. */
    private function pastikanKrKartu(KeyResult $keyResult): void
    {
        abort_unless($keyResult->source === 'kartu', 422, 'Key Result ini bukan bersumber kartu todolist.');
    }

    /**
     * Perbarui realisasi KR manual.
     *
     *  KR `auto` DITOLAK, bukan diam-diam diabaikan. Angka otomatis yang bisa
     *  ditimpa tangan berhenti bisa dipercaya — dan kalau ditolak diam-diam,
     *  pemakai mengira angkanya tersimpan padahal tidak.
     */
    public function updateActual(Request $request, KeyResult $keyResult)
    {
        if ($keyResult->source !== 'manual') {
            $sebab = $keyResult->source === 'kartu'
                ? 'menghitung kartu todolist yang selesai'
                : 'mengambil angkanya dari Insight/Pembukuan';
            throw ValidationException::withMessages([
                'actual_manual' => "Key Result ini $sebab dan tidak bisa diisi manual.",
            ]);
        }

        $data = $request->validate(['actual_manual' => 'required|numeric|min:0']);
        $keyResult->update($data);

        return back()->with('status', 'Realisasi diperbarui.');
    }

    private function validasiKeyResult(Request $request, ?KeyResult $existing = null): array
    {
        $data = $request->validate([
            'objective_id' => [$existing ? 'nullable' : 'required', 'exists:objectives,id'],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'source' => ['required', Rule::in(array_keys(KeyResult::SOURCES))],
            'board_key' => [
                'nullable',
                // Board kini opsional: KR kartu tanpa board menghitung kartu
                // yang ditautkan langsung lewat key_result_id, bukan seluruh
                // kartu di suatu board. Tanpa board target wajib diisi manual.
                Rule::exists('categories', 'key')->where('type', 'kanban'),
            ],
            // Metrik WAJIB saat source=auto: tanpanya KR itu tak punya sumber
            // angka sama sekali & akan selamanya menampilkan 0.
            'metric' => ['nullable', 'required_if:source,auto', Rule::in(array_keys(OkrMetrics::METRICS))],
            'target' => 'nullable|numeric|min:0',
            'unit' => ['required', Rule::in(array_keys(KeyResult::UNITS))],
            'priority_name' => 'nullable|string|max:50',
        ]);

        // Bersihkan kolom yang tak dipakai tiap sumber, supaya nilai lama tak
        // tertinggal saat sumbernya diubah:
        //   auto  — realisasi dihitung, actual_manual dikosongkan.
        //   kartu — realisasi = kartu selesai; metric & actual_manual tak
        //           berlaku, dan satuannya selalu 'angka' (menghitung kartu).
        //   manual— metric tak berlaku.
        if ($data['source'] === 'auto') {
            $data['board_key'] = null;
            $data['actual_manual'] = null;
            $data['unit'] = OkrMetrics::UNITS[$data['metric']];
            if (($data['target'] ?? null) === null) {
                throw ValidationException::withMessages([
                    'target' => 'Target wajib diisi untuk KR otomatis.',
                ]);
            }
        } elseif ($data['source'] === 'kartu') {
            $data['metric'] = null;
            $data['actual_manual'] = null;
            $data['unit'] = 'angka';
            if (! empty($data['board_key'])) {
                // Board dipilih → target & realisasi dihitung dari seluruh
                // kartu di board tersebut dalam kuartal, via target kuartal
                // board yang ditetapkan di /kpi.
                $objective = $existing?->objective
                    ?? Objective::findOrFail($data['objective_id']);
                $data['target'] = BoardQuarterTarget::for(
                    $data['board_key'],
                    $objective->year,
                    $objective->quarter
                )?->target_done ?? 0;
            } elseif (($data['target'] ?? null) === null) {
                // Tanpa board → KR ini mengukur kartu yang ditautkan langsung
                // lewat key_result_id (langkah per KR). Target = jumlah kartu
                // yang harus diselesaikan — diisi manual di form.
                throw ValidationException::withMessages([
                    'target' => 'Tanpa board Kanban, target jumlah kartu wajib diisi.',
                ]);
            }
        } else {
            $data['board_key'] = null;
            $data['metric'] = null;
            if (($data['target'] ?? null) === null) {
                throw ValidationException::withMessages([
                    'target' => 'Target wajib diisi.',
                ]);
            }
        }

        return $this->denganPrioritas($data);
    }

    /**
     * Ubah nama pilihan menjadi snapshot badge {name,color}.
     *
     * Warna tidak dipercaya dari browser. Server selalu mengambilnya dari
     * tabel labels dan hanya menerima Urgent/Penting pada grup penanda kerja.
     */
    private function denganPrioritas(array $data): array
    {
        $name = $data['priority_name'] ?? null;
        unset($data['priority_name']);

        if (blank($name)) {
            $data['priority'] = null;

            return $data;
        }

        $label = Label::where('group', 2)
            ->whereIn('name', self::PRIORITY_NAMES)
            ->where('name', $name)
            ->first(['name', 'color']);

        if (! $label) {
            throw ValidationException::withMessages([
                'priority_name' => 'Status harus Urgent atau Penting.',
            ]);
        }

        $data['priority'] = ['name' => $label->name, 'color' => $label->color];

        return $data;
    }
}
