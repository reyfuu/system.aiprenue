<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\BoardQuarterTarget;
use App\Models\Category;
use App\Models\KeyResult;
use App\Models\Label;
use App\Models\Output;
use App\Models\Pipeline;
use App\Models\User;
use App\Support\ExchangeRate;
use App\Support\Quarter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PipelineController extends Controller
{
    /** Sales pipeline — board tipe `pipeline`, dirender pakai UI kanban yang sama.
     *  Tanpa galeri: board dipilih lewat dropdown toolbar. */
    public function index(Request $request)
    {
        $categories = Pipeline::categories('pipeline');
        $keys = array_keys($categories);

        // ?category tak valid → board sales (menu ini = Sales Pipeline), fallback board pertama.
        // ponytail: key 'sales' di-hardcode; kalau nanti perlu board default yg bisa diatur,
        // tambah flag `is_default` di tabel categories.
        $default = in_array('sales', $keys, true) ? 'sales' : ($keys[0] ?? null);
        $category = in_array($request->category, $keys, true) ? $request->category : $default;
        if ($category === null) {
            abort(404, 'Belum ada board pipeline.');
        }

        return $this->renderBoard($request, $category, $categories, '/pipelines', 'Sales Pipeline', false);
    }

    /** Kanban LUAR: galeri semua board dikelompokkan per section. */
    private function gallery()
    {
        $boards = Category::where('type', 'kanban')->orderBy('name')->get();
        $counts = Pipeline::whereNull('archived_at')->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')->pluck('total', 'category')->toArray();

        // Buat/hapus BOARD = struktur, tetap owner/manager/it/admin (BoardTest).
        // Staff cuma mengelola KARTU di dalam board, bukan bikin board baru.
        return Inertia::render('BoardGallery', [
            'boards' => $boards->map(fn ($b) => [
                'key' => $b->key,
                'name' => $b->name,
                'section' => $b->section ?: 'Tanpa Grup',      // grup galeri
                'super_admin_only' => (bool) $b->super_admin_only,
                'count' => $counts[$b->key] ?? 0,             // jml task aktif
            ]),
            'canManage' => auth()->user()->canManage(),
        ]);
    }

    public function kanban(Request $request)
    {
        // Tanpa ?category → tampilkan galeri board (kanban luar)
        if (! $request->filled('category')) {
            return $this->gallery();
        }
        $categories = Pipeline::categories('kanban');                // hanya board tipe kanban
        // ?category tak valid → balik ke galeri
        if (! array_key_exists($request->category, $categories)) {
            return redirect()->route('pipelines.kanban');
        }

        return $this->renderBoard($request, $request->category, $categories, '/pipelines/kanban', 'Kanban', true);
    }

    /** Susun & render satu board (Kanban.vue) — dipakai Sales Pipeline & Kanban.
     *  $categories = board yg boleh dipilih di dropdown (sudah difilter per type),
     *  $baseUrl    = path modul (dipakai switch board & toggle arsip),
     *  $showGallery= tampilkan link balik ke galeri (kanban saja). */
    private function renderBoard(Request $request, string $category, array $categories, string $baseUrl, string $title, bool $showGallery)
    {
        // Tampilkan kartu aktif; bila ?archived=1 → tampilkan yg diarsipkan
        $showArchived = $request->boolean('archived');

        // Filter jenis — bisa banyak sekaligus (?jenis[]=endorse&jenis[]=speaker).
        // Nilai ngawur dibuang lewat intersect, BUKAN divalidasi: ?jenis ngawur jangan
        // bikin halaman error, cukup diabaikan.
        // Difilter di QUERY, bukan di Vue: SortableJS memutasi array kolom langsung saat
        // drag, jadi array hasil filter di frontend akan merusak drag & drop.
        // NB: bentuk UI-nya WAJIB chip, bukan dropdown. Versi dropdown pernah ada dan
        // dibuang — letaknya sama dgn dropdown board lama, jadi terbaca "pindah board".
        $jenis = array_values(array_intersect(
            array_map('strval', (array) $request->input('jenis', [])),
            array_keys(Pipeline::JENIS)
        ));

        // Filter KUARTAL (?q=YYYY-Qn). Dasarnya DEADLINE kartu — kuartal di sini
        // menjawab "apa yang harus selesai periode ini", bukan "apa yang
        // kebetulan dibuat periode ini". Konsekuensinya kartu TANPA deadline
        // tak pernah muncul saat filter aktif; itu disengaja, dan panel target
        // di UI menyebutkan jumlahnya supaya tak terlihat seperti kartu hilang.
        // Tanpa ?q → tidak menyaring apa pun; panel target tetap memakai kuartal
        // berjalan supaya halaman selalu punya angka acuan.
        $quarterPilih = Quarter::parse($request->query('q'));      // null = tak menyaring
        $quarterPanel = $quarterPilih ?? Quarter::current();
        [$qStart, $qEnd] = Quarter::range($quarterPanel['year'], $quarterPanel['quarter']);

        $pipelines = Pipeline::where('category', $category)
            ->with(['outputs', 'assignee', 'creator', 'comments.user', 'attachments.user'])
            ->when($showArchived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when($jenis, fn ($q) => $q->whereIn('jenis', $jenis))
            ->when($quarterPilih, fn ($q) => $q->whereBetween('deadline', [$qStart->toDateString(), $qEnd->toDateString()]))
            // Date Marker = tanggal kartu dibuat, bukan deadline. Batas awal/akhir
            // berdiri sendiri supaya pengguna boleh mengisi salah satunya saja.
            ->when($request->filled('created_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->created_from))
            ->when($request->filled('created_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->created_to))
            // position = urutan hasil drag. `id` DESC sbg pemecah seri: saat banyak
            // kartu sama-sama position 0 (baru dibuat, belum pernah di-drag), yang
            // TERBARU (id terbesar) muncul paling atas — kartu baru itu yang sedang
            // dikerjakan, bukan yang terlupakan di dasar tumpukan. Sesudah di-drag,
            // position jadi distinct sehingga tie-break ini tak lagi terpakai.
            ->orderBy('position')->orderBy('id', 'desc')->get();

        // Jumlah kartu per jenis untuk angka di chip — TIDAK ikut $jenis, kalau ikut
        // angkanya jadi 0 begitu chip lain dipilih & tak bisa dipakai memilih.
        // Ikut $showArchived supaya cocok dgn apa yang sedang ditampilkan.
        $jenisCounts = Pipeline::where('category', $category)
            ->when($showArchived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->whereNotNull('jenis')
            ->selectRaw('jenis, COUNT(*) as total')->groupBy('jenis')->pluck('total', 'jenis')->toArray();

        // Estimasi nilai SELURUH board. Sengaja dari query terpisah yang tak ikut
        // $jenis: menjumlah kartu yang tampil (spt boardValue di Vue) bikin angkanya
        // menyusut saat chip dipilih — itu total tersaring, bukan total board.
        $rate = ExchangeRate::usdToIdr();
        $nilai = Pipeline::where('category', $category)
            ->when($showArchived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->selectRaw('COALESCE(SUM(amount_idr),0) as idr, COALESCE(SUM(amount_usd),0) as usd')->first();
        $boardTotal = (float) $nilai->idr + (float) $nilai->usd * $rate;

        // Hitung kartu AKTIF per kategori (arsip tidak dihitung)
        $counts = Pipeline::whereNull('archived_at')->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')->pluck('total', 'category')->toArray();
        $counts = array_merge(array_fill_keys(array_keys($categories), 0), $counts);

        // Jumlah kartu di arsip board ini (untuk tombol toggle)
        $archivedCount = Pipeline::where('category', $category)->whereNotNull('archived_at')->count();

        // Kolom board ini + susun kartu per kolom (derivasi pindah dari blade @php)
        $columns = BoardColumn::forBoard($category);
        $colKeys = $columns->pluck('key')->all();
        $board = array_fill_keys($colKeys, []); // kolom kosong per key
        foreach ($pipelines as $p) {
            // kartu dgn kolom terhapus → jatuh ke kolom pertama
            $ck = in_array($p->progress, $colKeys, true) ? $p->progress : ($colKeys[0] ?? $p->progress);
            $board[$ck][] = [
                'id' => $p->id,
                'code' => 't_'.str_pad($p->id, 6, '0', STR_PAD_LEFT),
                'created_date' => $p->created_at?->toDateString(),
                'endorse' => $p->endorse,
                'jenis' => $p->jenis,                                               // key mentah (form edit)
                'jenis_label' => $p->jenis ? (Pipeline::JENIS[$p->jenis] ?? $p->jenis) : null,
                'account' => Pipeline::ACCOUNTS[$p->account] ?? $p->account,          // label akun
                'account_color' => Pipeline::ACCOUNT_COLORS[$p->account] ?? 'bg-slate-500 text-white',
                'outputs' => $p->outputs->pluck('name'),
                'payment' => Pipeline::PAYMENT[$p->payment_status] ?? $p->payment_status,
                'payment_status' => $p->payment_status,
                'amount_idr' => $p->amount_idr,
                'amount_usd' => $p->amount_usd,
                // cicilan DP + berapa kali sudah DP (slot terisi & > 0)
                'dp1' => $p->dp1,
                'dp2' => $p->dp2,
                'dp3' => $p->dp3,
                'dp_count' => collect([$p->dp1, $p->dp2, $p->dp3])->filter(fn ($v) => (float) $v > 0)->count(),
                'assignee' => $p->assignee?->name,
                // Pembuat kartu. null = kartu lama (dibuat sebelum kolomnya ada) —
                // UI menampilkannya sbg '—', bukan menebak siapa pun.
                'created_by_name' => $p->creator?->name,
                // Ketepatan waktu: 'tepat' | 'terlambat' | 'lewat' | null.
                'ketepatan' => $p->ketepatan(),
                'completed_at' => $p->completed_at?->toDateString(),
                'link' => $p->link,
                'labels' => $p->labels ?? [],
                // Tautan ke Key Result OKR (todolist saja). null = tak tertaut.
                'key_result_id' => $p->key_result_id,
                'key_result_title' => $p->keyResult?->title,
                'done' => (bool) $p->done,                         // kartu ditandai selesai (ala Trello)
                // fitur kartu: deadline, deskripsi, arsip
                'deadline' => $p->deadline?->toDateString(),
                'description' => $p->description,
                'archived' => (bool) $p->archived_at,
                // komentar (terbaru dulu) + lampiran
                'comments' => $p->comments->sortByDesc('created_at')->values()->map(fn ($c) => [
                    'id' => $c->id,
                    'body' => $c->body,
                    'user' => $c->user?->name,
                    'user_id' => $c->user_id,
                    'time' => $c->created_at?->diffForHumans(),
                ]),
                'comment_count' => $p->comments->count(),
                'attachments' => $p->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'url' => $a->url,
                    'size' => $a->size,
                    'user' => $a->user?->name,
                ]),
                'attachment_count' => $p->attachments->count(),
                // field mentah utk form edit
                'account_key' => $p->account,
                'assigned_to' => $p->assigned_to,
                'progress' => $p->progress,
                'output_ids' => $p->outputs->pluck('id'),
                'notes' => $p->notes,
                // kontak lead (WA / Gmail / DM IG) — tampil di modal detail
                'kontak_wa' => $p->kontak_wa,
                'kontak_gmail' => $p->kontak_gmail,
                'kontak_ig' => $p->kontak_ig,
            ];
        }

        $currentBoard = Category::where('key', $category)->first();

        // ---- Panel kuartal: target KPI + capaian + ketepatan waktu ----
        // ---- Capaian kuartal board: HANYA untuk peran pengelola ----
        //
        // Ini penilaian kinerja tim (berapa target tercapai, berapa kali telat),
        // bukan alat kerja. Staff punya menu `kanban`, jadi tanpa gerbang ini ia
        // ikut membaca rapor seluruh board.
        //
        // Gerbangnya di SERVER, bukan `v-if` di Vue: props Inertia terbaca utuh
        // di source halaman (`data-page`), jadi menyembunyikannya di frontend
        // tidak menutup apa pun. Query-nya pun tak dijalankan — percuma
        // menghitung yang tak akan dikirim.
        //
        // Dipakai canManage() (owner/manager/it/admin), BUKAN canSee('kpi'):
        // menu `kpi` kini terbuka untuk staff supaya ia bisa membuka rapornya
        // sendiri, sehingga canSee('kpi') akan meloloskan staff di sini juga.
        $quarterStats = null;
        if (auth()->user()->canManage()) {
            // Sengaja query TERPISAH yang tak ikut $jenis/$showArchived/filter tanggal,
            // dgn alasan yang sama seperti $boardTotal di atas: ini capaian BOARD pada
            // kuartal itu, bukan capaian dari apa yang kebetulan sedang tersaring di
            // layar. Rumusnya dijaga identik dgn KpiController::statistik() — kalau
            // menyimpang, angka board di halaman Kanban & halaman KPI akan berselisih
            // untuk kuartal yang sama.
            $kartuKuartal = Pipeline::where('category', $category)
                ->whereBetween('deadline', [$qStart->toDateString(), $qEnd->toDateString()])
                ->get(['id', 'deadline', 'completed_at']);
            $selesaiKuartal = $kartuKuartal->whereNotNull('completed_at')->count();
            $target = (int) (BoardQuarterTarget::for($category, $quarterPanel['year'], $quarterPanel['quarter'])?->target_done ?? 0);

            $quarterStats = [
                'total' => $kartuKuartal->count(),
                'done' => $selesaiKuartal,
                'target' => $target,
                // null saat target belum ditetapkan — bedakan dari 0%, lihat KpiController.
                'percent' => $target > 0 ? round($selesaiKuartal / $target * 100, 1) : null,
                // Kartu tanpa deadline: tak masuk kuartal mana pun. Jumlahnya dikirim
                // supaya UI bisa menjelaskan selisih antara isi board & isi panel —
                // tanpa ini, filter kuartal terlihat seperti menghilangkan kartu.
                'no_deadline' => Pipeline::where('category', $category)
                    ->whereNull('archived_at')->whereNull('deadline')->count(),
                'ketepatan' => KpiController::hitungKetepatan($kartuKuartal),
            ];
        }

        return Inertia::render('Kanban', [
            // Kuartal yang sedang dipakai panel + status apakah kartunya ikut disaring.
            'quarter' => [
                'year' => $quarterPanel['year'],
                'quarter' => $quarterPanel['quarter'],
                'key' => $quarterPanel['year'].'-Q'.$quarterPanel['quarter'],
                'label' => Quarter::label($quarterPanel['year'], $quarterPanel['quarter']),
                'filtering' => $quarterPilih !== null,     // false = panel saja, kartu tak disaring
            ],
            'quarterOptions' => Quarter::options(),
            // null = peran ini tak berhak melihat capaian board. Vue merendernya
            // dgn v-if; filter kuartal di atas tetap tampil untuk semua.
            'quarterStats' => $quarterStats,
            // Key Result bersumber 'kartu' yang bisa dituju kartu di board ini.
            // Hanya untuk board todolist (keputusan "todolist saja"); board lain
            // dapat array kosong & pemilih KR-nya tak muncul. Dikirim untuk
            // SEMUA peran yang boleh melihat board — menautkan kartu ke goal
            // adalah bagian mengerjakannya, bukan data kinerja rahasia.
            'keyResults' => $category === 'todolist' ? KeyResult::keyResultKartuAktif() : [],
            'category' => $category,
            'counts' => $counts,
            'categories' => $categories,                                  // board select: sesuai type modul
            'baseUrl' => $baseUrl,                                     // '/pipelines' | '/pipelines/kanban'
            'pageTitle' => $title,
            'showGallery' => $showGallery,                                 // link galeri: kanban saja
            // Board baru dari halaman ini harus bertipe sama, kalau tidak langsung hilang dari modul ini
            'boardType' => $currentBoard?->type ?? 'kanban',
            // Kurs USD→IDR: nilai deal per stage dijumlahkan dalam IDR (kartu bisa USD).
            'rate' => $rate,
            'boardTotal' => $boardTotal,                                 // estimasi nilai SELURUH board (tak ikut filter)
            'board' => $board,                                       // kartu tersusun per kolom
            'columns' => $columns,                                     // kolom dinamis board ini
            'jenis' => $jenis,                                      // chip aktif (array; kosong = semua)
            'dateFilters' => $request->only(['created_from', 'created_to']),
            'jenisCounts' => $jenisCounts,                                // angka di tiap chip
            'showArchived' => $showArchived,                               // sedang lihat arsip?
            'archivedCount' => $archivedCount,                             // jumlah kartu diarsip
            'staff' => User::orderBy('name')->get(['id', 'name', 'role']),
            'outputs' => Output::orderBy('name')->get(),
            'canManage' => auth()->user()->canManageBoard($category),      // KARTU: staff boleh di board kanban, bukan Sales
            'canManageStructure' => auth()->user()->canManage(),           // KOLOM/BOARD/LAMPIRAN: owner/manager/it/admin saja
            'currentBoard' => $currentBoard,
            // Pembuat board. null utk board bawaan seeder & board lama.
            'boardCreator' => $currentBoard?->creator?->name,
            // Definisi label (dikelola owner) untuk picker & pengelolaan di modal.
            'labels' => Label::orderBy('id')->get(['id', 'name', 'color']),
            // Referensi untuk form tambah/edit kartu
            'accounts' => Pipeline::ACCOUNTS,
            'jenisList' => Pipeline::JENIS,          // endorse/coaching/agensi/speaker
            'payments' => Pipeline::PAYMENT,
        ]);
    }

    /** Simpan isi & urutan satu kolom kanban sekaligus.
     *
     *  Menggantikan endpoint per-kartu yang lama (`{pipeline}/progress`). Dulu
     *  ia cuma menyimpan "kartu ini pindah ke kolom mana", jadi menggeser kartu
     *  naik/turun di dalam kolom yang sama tak tersimpan sama sekali.
     *
     *  Yang dikirim klien = daftar id kolom tujuan sesudah drag, terurut.
     *  Bentuk itu memuat KEDUA kejadiannya: pindah antar kolom & geser di dalam
     *  kolom sama-sama menghasilkan "kolom B sekarang berisi id-id ini, urutan
     *  segini". Satu endpoint, satu perjalanan jaringan, tak ada keadaan
     *  setengah jadi seperti kalau progress & urutan dikirim terpisah.
     *
     *  Kolom ASAL tak perlu ikut diperbarui: posisinya boleh berlubang
     *  (0,1,3,...) karena yang dipakai cuma urutan relatifnya. */
    public function reorder(Request $request)
    {
        $data = $request->validate([
            'progress' => ['required', 'string'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $cards = Pipeline::whereIn('id', $data['ids'])->get();

        abort_if($cards->count() !== count($data['ids']), 404, 'Ada kartu yang tak ditemukan.');

        // Semua kartu wajib satu board. Board diambil DARI KARTUNYA, bukan dari
        // request: key kolom tak unik antar board (dua board bisa sama-sama punya
        // 'script'), jadi memvalidasi progress tanpa tahu boardnya = membiarkan
        // kartu dipindah ke kolom milik board lain.
        $category = $cards->pluck('category')->unique();
        abort_if($category->count() > 1, 422, 'Kartu berasal dari board berbeda.');

        // Terurut posisi (forBoard), bukan pluck mentah: kolom TERAKHIR dipakai
        // sbg penanda "pekerjaan rampung" di bawah, dan urutan hasil query tanpa
        // ORDER BY tidak dijamin — "terakhir" bisa jadi kolom yang keliru.
        $kolom = BoardColumn::forBoard($category->first());
        $validKeys = $kolom->pluck('key')->all();
        abort_unless(in_array($data['progress'], $validKeys, true), 422, 'Kolom tak dikenal di board ini.');

        // Kolom paling kanan = tahap selesai. Memakai POSISI, bukan mencocokkan
        // nama/key 'done': kolom board dinamis & bisa dinamai apa saja
        // ('Published', 'Tayang', 'Selesai'), jadi pencocokan kata akan gagal
        // diam-diam di board yang tak memakai kata itu.
        $kolomSelesai = $kolom->last()?->key;
        $keKolomSelesai = $kolomSelesai !== null && $data['progress'] === $kolomSelesai;

        // Transaksi: separuh tersimpan = urutan kacau di layar semua orang.
        DB::transaction(function () use ($data, $cards, $keKolomSelesai) {
            foreach ($data['ids'] as $i => $id) {
                $ubah = ['progress' => $data['progress'], 'position' => $i];

                // Drag masuk/keluar kolom terakhir ikut menggerakkan stempel
                // selesai — tanpa ini, kartu yang diselesaikan lewat drag (cara
                // paling lazim) tak pernah punya completed_at & luput dari
                // analitik ketepatan. Stempel lama dipertahankan, lihat
                // stempelSelesai().
                //
                // TAPI hanya untuk kartu yang benar-benar BERPINDAH kolom.
                // Kiriman drag berisi SELURUH isi kolom tujuan (lihat
                // Kanban.vue), jadi kartu yang sudah lama duduk di situ ikut
                // terbawa cuma karena posisinya bergeser. Menstempel mereka
                // juga berarti satu drag menimpa waktu selesai semua kartu
                // lama dgn "hari ini" — deadline mereka sudah lewat, jadi
                // seluruh papan mendadak terbaca terlambat.
                $kartu = $cards->firstWhere('id', $id);

                if ($kartu->progress !== $data['progress']) {
                    $ubah['completed_at'] = $this->stempelSelesai($kartu, $keKolomSelesai);
                }

                Pipeline::where('id', $id)->update($ubah);
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Tandai kartu selesai / batal (flag `done`, tak memindah kolom). */
    public function updateDone(Request $request, Pipeline $pipeline)
    {
        $data = $request->validate(['done' => 'required|boolean']);
        $pipeline->update($data + ['completed_at' => $this->stempelSelesai($pipeline, $data['done'])]);

        return response()->json(['ok' => true]);
    }

    /**
     * Nilai baru `completed_at` saat status selesai kartu berubah.
     *
     *  Dibatalkan → null: kartu yang dibuka lagi tak boleh menyisakan stempel
     *  selesai, kalau tidak ia terhitung di analitik ketepatan padahal
     *  pekerjaannya masih berjalan.
     *
     *  Diselesaikan → stempel LAMA dipertahankan bila sudah ada. Kartu yang
     *  ditandai selesai dua kali (mis. lewat tombol lalu lewat drag ke kolom
     *  terakhir) harus tetap memakai waktu penyelesaian pertama — kalau
     *  ditimpa, kartu terlambat bisa "dirapikan" jadi tepat waktu hanya dgn
     *  menekan tombolnya ulang.
     */
    private function stempelSelesai(Pipeline $pipeline, bool $selesai): ?string
    {
        if (! $selesai) {
            return null;
        }

        return ($pipeline->completed_at ?? now())->toDateTimeString();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        // Pencatat kartu = user yang menekan simpan. Diambil dari sesi, tak
        // pernah dari request: kalau dari request, siapa pun bisa mengaku
        // sbg orang lain hanya dgn menyisipkan satu field.
        $pipeline = Pipeline::create($data + ['created_by' => $request->user()?->id]);
        $pipeline->outputs()->sync($request->input('outputs', []));

        // Lampiran opsional saat membuat kartu (jpeg/pdf/dll). Kartu belum punya id
        // sebelum dibuat, jadi filenya ikut di request buat-kartu — bukan endpoint
        // /attachments terpisah. Logika sama dgn AttachmentController::store.
        if ($request->hasFile('newAttachment')) {
            $request->validate(['newAttachment' => 'file|max:10240']);   // maks 10 MB
            $file = $request->file('newAttachment');
            $pipeline->attachments()->create([
                'user_id' => $request->user()->id,
                'path' => $file->store('attachments', 'public'),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return redirect()->back()->with('status', 'Entri ditambahkan.');
    }

    public function update(Request $request, Pipeline $pipeline)
    {
        $data = $this->validated($request);
        $pipeline->update($data);
        $pipeline->outputs()->sync($request->input('outputs', []));

        return redirect()->back()->with('status', 'Entri diperbarui.');
    }

    public function destroy(Pipeline $pipeline)
    {
        $pipeline->delete();

        return redirect()->back()->with('status', 'Entri dihapus.');
    }

    /** Arsipkan / kembalikan kartu (toggle archived_at). */
    public function archive(Pipeline $pipeline)
    {
        $archiving = is_null($pipeline->archived_at);                 // sedang mengarsip?
        $pipeline->update(['archived_at' => $archiving ? now() : null]);

        return redirect()->back()->with('status', $archiving ? 'Kartu diarsipkan.' : 'Kartu dikembalikan.');
    }

    private function validated(Request $request): array
    {
        $validProgress = BoardColumn::where('board_key', $request->category)->pluck('key')->all();

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Pipeline::categories()))],
            'jenis' => ['nullable', Rule::in(array_keys(Pipeline::JENIS))],
            'account' => ['required', Rule::in(array_keys(Pipeline::ACCOUNTS))],
            'assigned_to' => 'nullable|exists:users,id',
            'link' => 'nullable|url|max:2048',
            // Kontak lead — string bebas, bukan url/email ketat: WA sering ditulis
            // '0812…' atau '+62…', IG '@akun'. Validasi kaku malah menolak isian wajar.
            'kontak_wa' => 'nullable|string|max:40',
            'kontak_gmail' => 'nullable|string|max:255',
            'kontak_ig' => 'nullable|string|max:100',
            // Satu label per kartu (pilih-satu, ala radio). max:1 ditegakkan di
            // sini juga, bukan cuma di pemilih Vue: request langsung tetap
            // tembus kalau gerbangnya hanya di frontend. Kartu lama yang
            // terlanjur berlabel banyak tak tersentuh — validasi ini hanya
            // berlaku untuk kiriman baru.
            'labels' => 'nullable|array|max:1',
            'labels.*.name' => 'required_with:labels|string|max:50',
            'labels.*.color' => 'required_with:labels|string|max:40',
            'coaching' => 'nullable|string|max:255',
            'speaker' => 'nullable|string|max:255',
            'endorse' => 'required|string|max:255',
            'description' => 'nullable|string',
            'progress' => ['required', Rule::in($validProgress ?: ['script'])],
            'tanggal_posting' => 'nullable|date',
            'tanggal_payment' => 'nullable|date',
            'deadline' => 'nullable|date',
            'payment_status' => 'required|in:belum,dp,lunas',
            'amount_idr' => 'nullable|numeric|min:0',
            'amount_usd' => 'nullable|numeric|min:0',
            'dp1' => 'nullable|numeric|min:0',
            'dp2' => 'nullable|numeric|min:0',
            'dp3' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'outputs' => 'array',
            'outputs.*' => 'exists:outputs,id',
            // Tautan ke Key Result (langkah menuju goal OKR). Divalidasi lanjut
            // di bawah: hanya board todolist & hanya KR bersumber 'kartu'.
            'key_result_id' => 'nullable|exists:key_results,id',
        ]);

        $data['key_result_id'] = $this->tautanKrValid($data['category'], $data['key_result_id'] ?? null);

        return $data;
    }

    /**
     * Saring tautan KR sebuah kartu ke nilai yang sah, atau null.
     *
     *  Dua pagar, ditegakkan di SERVER bukan cuma disembunyikan di Vue:
     *   1. Hanya kartu board 'todolist' yang boleh menautkan — board lain
     *      (Sales, produksi) tak ikut, sesuai keputusan "todolist saja".
     *   2. KR yang dituju WAJIB bersumber 'kartu'. Menautkan ke KR 'auto'
     *      (view/omset) atau 'manual' tak punya arti: realisasinya tak
     *      dihitung dari kartu, jadi tautannya cuma menyesatkan.
     *
     *  Melanggar salah satunya → tautan dibuang jadi null, bukan 4xx: kartu
     *  tetap tersimpan, ia hanya tak jadi tertaut. Menolak seluruh simpanan
     *  kartu gara-gara satu field turunan terlalu keras.
     */
    private function tautanKrValid(string $category, ?int $keyResultId): ?int
    {
        if ($keyResultId === null || $category !== 'todolist') {
            return null;
        }

        return KeyResult::where('id', $keyResultId)->where('source', 'kartu')->exists()
            ? $keyResultId
            : null;
    }
}
