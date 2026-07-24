<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\KeyResult;
use App\Models\Objective;
use App\Models\Pipeline;
use App\Models\User;
use App\Support\OkrMetrics;
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
 *  Halaman ini TERKUNCI untuk owner & manager lewat User::canSee(), sejajar
 *  dgn pembukuan & tracking: isinya omset dan pertumbuhan audiens. KPI board
 *  dan rapor per orang ada di KpiController — audiensnya lebih luas.
 *
 *  Kuartal dipilih lewat ?q=YYYY-Qn; tanpa itu memakai kuartal berjalan.
 */
class OkrController extends Controller
{
    /** Berapa kuartal ke belakang yang ditarik untuk grafik tren. */
    private const TREN_KUARTAL = 6;

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
        $krKartuIds = $daftar->flatMap->keyResults->where('source', 'kartu')->pluck('id');
        $kartuPerKr = Pipeline::whereIn('key_result_id', $krKartuIds)
            ->orderBy('position')->orderBy('id')
            ->get(['id', 'key_result_id', 'endorse', 'progress', 'deadline', 'completed_at'])
            ->groupBy('key_result_id');

        $objectives = $daftar->map(fn (Objective $o) => [
            'id' => $o->id,
            'title' => $o->title,
            'description' => $o->description,
            'progress' => $o->progress($realisasi),
            'created_by_name' => $o->creator?->name,
            'key_results' => $o->keyResults->map(function (KeyResult $kr) use ($realisasi, $kartuPerKr) {
                $kartu = $kartuPerKr->get($kr->id, collect());
                if ($kr->source === 'kartu') {
                    $kr->setAttribute('kartu_selesai', $kartu->whereNotNull('completed_at')->count());
                }

                return [
                    'id' => $kr->id,
                    'title' => $kr->title,
                    'source' => $kr->source,
                    'source_label' => KeyResult::SOURCES[$kr->source] ?? $kr->source,
                    'metric' => $kr->metric,
                    'unit' => $kr->unit,
                    'target' => (float) $kr->target,
                    'actual' => $kr->actual($realisasi),
                    'percent' => $kr->percent($realisasi),
                    'owner_name' => $kr->owner?->name,
                    // Daftar langkah, hanya untuk KR bersumber 'kartu'. KR lain
                    // dapat array kosong — Vue merendernya bersyarat.
                    'kartu' => $kr->source === 'kartu' ? $kartu->map(fn (Pipeline $p) => [
                        'id' => $p->id,
                        'judul' => $p->endorse,
                        'selesai' => $p->completed_at !== null,
                        'ketepatan' => $p->ketepatan(),
                    ])->values() : [],
                ];
            })->values(),
        ])->values();

        return Inertia::render('Okr', [
            'quarter' => ['year' => $year, 'quarter' => $quarter, 'key' => $year.'-Q'.$quarter, 'label' => Quarter::label($year, $quarter)],
            'quarterOptions' => Quarter::options(),
            'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'objectives' => $objectives,
            'ringkasan' => $this->ringkasan($objectives),
            'tren' => $this->tren($year, $quarter),
            'metrics' => OkrMetrics::METRICS,
            'sources' => KeyResult::SOURCES,
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
    private function ringkasan($objectives): array
    {
        $krs = $objectives->pluck('key_results')->flatten(1);
        $persenObjective = $objectives->pluck('progress')->filter(fn ($p) => $p !== null);
        $persenKr = $krs->pluck('percent')->filter(fn ($p) => $p !== null);

        return [
            'objectives' => $objectives->count(),
            'key_results' => $krs->count(),
            'progress' => $persenObjective->isEmpty() ? null : round($persenObjective->avg(), 1),
            'tercapai' => $persenKr->filter(fn ($p) => $p >= 100)->count(),
            'tertinggal' => $persenKr->filter(fn ($p) => $p < 60)->count(),
        ];
    }

    /**
     * Tren tiap metrik otomatis selama beberapa kuartal terakhir.
     *
     *  Target diambil dari KR `auto` bermetrik sama di kuartal itu. Bila satu
     *  kuartal punya lebih dari satu KR untuk metrik yang sama (dibolehkan —
     *  dua Objective berbeda boleh mengejar metrik yang sama), targetnya
     *  DIJUMLAH. Mengambil yang pertama saja akan diam-diam menyembunyikan
     *  target yang lain.
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
            ->where('key_results.source', 'auto')->whereNotNull('key_results.metric')
            ->selectRaw('objectives.year, objectives.quarter, key_results.metric, SUM(key_results.target) as total')
            ->groupBy('objectives.year', 'objectives.quarter', 'key_results.metric')
            ->get()
            ->keyBy(fn ($r) => $r->year.'-'.$r->quarter.'-'.$r->metric);

        $out = [];
        foreach (OkrMetrics::METRICS as $metric => $label) {
            $titik = [];
            foreach ($periode as $p) {
                $t = (float) ($target[$p['year'].'-'.$p['quarter'].'-'.$metric]->total ?? 0);
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
        $data = $this->validasiObjective($request);
        $data['created_by'] = $request->user()->id;
        // Objective baru masuk paling bawah, bukan paling atas: urutan yang
        // sudah disusun pemakai tak boleh bergeser tiap kali ia menambah satu.
        $data['position'] = (int) Objective::where('year', $data['year'])
            ->where('quarter', $data['quarter'])->max('position') + 1;

        Objective::create($data);

        return back()->with('status', 'Objective ditambahkan.');
    }

    public function updateObjective(Request $request, Objective $objective)
    {
        $objective->update($this->validasiObjective($request));

        return back()->with('status', 'Objective diperbarui.');
    }

    /** Key Result ikut terhapus lewat cascadeOnDelete di skema — tanpa
     *  Objective ia tak punya arti. */
    public function destroyObjective(Objective $objective)
    {
        $objective->delete();

        return back()->with('status', 'Objective dihapus.');
    }

    private function validasiObjective(Request $request): array
    {
        return $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'quarter' => 'required|integer|min:1|max:4',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);
    }

    // --------------------------------------------------------- Key Result

    public function storeKeyResult(Request $request)
    {
        $data = $this->validasiKeyResult($request);
        $data['created_by'] = $request->user()->id;
        // PJ belum bisa dipilih di form — untuk sekarang selalu owner, sesuai
        // keputusan "sementara penanggung jawabnya owner dulu". Kolomnya sudah
        // FK ke users, jadi pemilih PJ nanti tinggal dipasang tanpa migrasi.
        $data['owner_id'] = User::where('role', 'owner')->orderBy('id')->value('id');
        $data['position'] = (int) KeyResult::where('objective_id', $data['objective_id'])->max('position') + 1;

        KeyResult::create($data);

        return back()->with('status', 'Key Result ditambahkan.');
    }

    public function updateKeyResult(Request $request, KeyResult $keyResult)
    {
        $data = $this->validasiKeyResult($request, $keyResult);
        unset($data['objective_id']);   // KR tak berpindah induk lewat form ini

        $keyResult->update($data);

        return back()->with('status', 'Key Result diperbarui.');
    }

    public function destroyKeyResult(KeyResult $keyResult)
    {
        $keyResult->delete();

        return back()->with('status', 'Key Result dihapus.');
    }

    // ------------------------------------------------- kartu (langkah) KR
    //
    //  Penautan kartu todolist ke KR dikelola DARI SINI, bukan dari Kanban.
    //  Kanban murni untuk delegasi; goal & langkah pencapaiannya tinggal di
    //  satu tempat (halaman OKR). Semua endpoint di bawah hanya untuk KR
    //  bersumber 'kartu' — menautkan langkah ke KR auto/manual tak berarti.

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
            'source' => ['required', Rule::in(array_keys(KeyResult::SOURCES))],
            // Metrik WAJIB saat source=auto: tanpanya KR itu tak punya sumber
            // angka sama sekali & akan selamanya menampilkan 0.
            'metric' => ['nullable', 'required_if:source,auto', Rule::in(array_keys(OkrMetrics::METRICS))],
            'target' => 'required|numeric|min:0',
            'unit' => ['required', Rule::in(array_keys(KeyResult::UNITS))],
        ]);

        // Bersihkan kolom yang tak dipakai tiap sumber, supaya nilai lama tak
        // tertinggal saat sumbernya diubah:
        //   auto  — realisasi dihitung, actual_manual dikosongkan.
        //   kartu — realisasi = kartu selesai; metric & actual_manual tak
        //           berlaku, dan satuannya selalu 'angka' (menghitung kartu).
        //   manual— metric tak berlaku.
        if ($data['source'] === 'auto') {
            $data['actual_manual'] = null;
        } elseif ($data['source'] === 'kartu') {
            $data['metric'] = null;
            $data['actual_manual'] = null;
            $data['unit'] = 'angka';
        } else {
            $data['metric'] = null;
        }

        return $data;
    }
}
