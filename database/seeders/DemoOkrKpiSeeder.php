<?php

namespace Database\Seeders;

use App\Models\BoardColumn;
use App\Models\BoardQuarterTarget;
use App\Models\Category;
use App\Models\InsightAccount;
use App\Models\InsightContent;
use App\Models\KeyResult;
use App\Models\Objective;
use App\Models\Pipeline;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Quarter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data demo untuk mencoba OKR, KPI board, filter kuartal, dan analitik
 * ketepatan waktu.
 *
 *  Semua yang dibuat di sini diberi penanda '[DEMO]' pada judul/kategori
 *  supaya bisa dikenali & dihapus tanpa menyentuh data asli. Dijalankan
 *  manual, TIDAK ikut DatabaseSeeder:
 *
 *      php artisan db:seed --class=DemoOkrKpiSeeder
 *
 *  Idempoten lewat firstOrCreate/updateOrCreate — menjalankannya dua kali
 *  tidak menggandakan apa pun.
 *
 *  Angkanya sengaja TIDAK semuanya hijau. Kuartal berjalan dibuat sedikit di
 *  bawah target dan berisi campuran kartu tepat/terlambat/lewat deadline;
 *  demo yang semuanya 100% tak memperlihatkan apakah warna & rasio ketepatan
 *  benar-benar bekerja.
 */
class DemoOkrKpiSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Seeder demo dilewati: environment produksi.');

            return;
        }

        $now = Quarter::current();
        $lalu = $this->kuartalSebelum($now);

        $this->tim();
        $this->okr($now, $lalu);
        $this->insight($now, $lalu);
        $this->omset($now, $lalu);
        $board = $this->board();
        $this->targetBoard($board, $now, $lalu);
        $this->kartu($board, $now, $lalu);

        $this->command?->info('Demo OKR & KPI dibuat untuk '.Quarter::label($now['year'], $now['quarter']).' dan '.Quarter::label($lalu['year'], $lalu['quarter']).'.');
    }

    /** Kuartal sebelum $q, ikut mundur tahun saat menyeberang Q1. */
    private function kuartalSebelum(array $q): array
    {
        return $q['quarter'] === 1
            ? ['year' => $q['year'] - 1, 'quarter' => 4]
            : ['year' => $q['year'], 'quarter' => $q['quarter'] - 1];
    }

    /**
     * Anggota tim demo berperan staff & admin.
     *
     *  DatabaseSeeder hanya membuat owner, dua manager, dan it — tanpa staff
     *  maupun admin, rapor per orang tak punya apa pun untuk diperlihatkan
     *  dan seluruh kartu mereka jatuh ke baris "Belum ditugaskan". Password
     *  sama dgn akun seeder lain supaya bisa dipakai mencoba hak akses tiap
     *  peran.
     */
    private function tim(): void
    {
        foreach ([
            ['name' => 'Budi Staff', 'email' => 'budi@example.com', 'role' => 'staff'],
            ['name' => 'Sari Admin', 'email' => 'sari@example.com', 'role' => 'admin'],
        ] as $u) {
            User::updateOrCreate(['email' => $u['email']], [
                'name' => $u['name'],
                'password' => Hash::make('password123'),
                'role' => $u['role'],
            ]);
        }
    }

    // ------------------------------------------------------------ OKR

    /**
     * Objective + Key Result dua kuartal.
     *
     *  Sengaja memuat KR `manual` juga, bukan hanya yang otomatis: alur
     *  "Perbarui angka" tak bisa dicoba kalau seluruh KR mengambil angkanya
     *  sendiri.
     */
    private function okr(array $now, array $lalu): void
    {
        $owner = User::where('role', 'owner')->orderBy('id')->value('id');

        // [judul objective, keterangan, [ [judul KR, source, metric, target, unit], ... ] ]
        $rencana = [
            [$lalu, 'Bangun fondasi kanal', 'Kuartal pertama yang diukur — angkanya jadi baseline.', [
                ['Total view seluruh konten', 'auto', 'view', 400000, 'angka'],
                ['Total subscriber semua kanal', 'auto', 'subscriber', 12000, 'angka'],
            ]],
            [$lalu, 'Buka aliran pendapatan coaching', null, [
                ['Omset kuartal', 'auto', 'omset', 180000000, 'rupiah'],
            ]],
            [$now, 'Jadi rujukan utama konten AI di Indonesia', 'Diukur dari jangkauan kanal & pertumbuhan audiens, bukan jumlah unggahan.', [
                ['Total view seluruh konten', 'auto', 'view', 750000, 'angka'],
                ['Total subscriber semua kanal', 'auto', 'subscriber', 20000, 'angka'],
                // Sumber 'kartu': realisasinya dari kartu todolist tertaut yang
                // selesai. Diisi oleh kartuGoal() di bawah.
                ['Kolaborasi dengan kreator lain', 'kartu', null, 5, 'angka'],
            ]],
            [$now, 'Bisnis coaching tumbuh sehat', 'Omset dari transaksi yang benar-benar masuk, bukan nilai deal di kartu Sales.', [
                ['Omset kuartal', 'auto', 'omset', 250000000, 'rupiah'],
                ['Klien coaching baru', 'manual', null, 10, 'angka'],
            ]],
        ];

        // Realisasi KR manual — hanya untuk yang manual; KR otomatis & 'kartu'
        // mengambil angkanya sendiri dan kolom ini WAJIB null di sana.
        $realisasiManual = ['Klien coaching baru' => 7];

        foreach ($rencana as $i => [$q, $judul, $ket, $krs]) {
            $objective = Objective::updateOrCreate(
                ['year' => $q['year'], 'quarter' => $q['quarter'], 'title' => $judul],
                ['description' => $ket, 'position' => $i, 'created_by' => $owner],
            );

            foreach ($krs as $j => [$krJudul, $source, $metric, $target, $unit]) {
                KeyResult::updateOrCreate(
                    ['objective_id' => $objective->id, 'title' => $krJudul],
                    [
                        'source' => $source,
                        'metric' => $metric,
                        'target' => $target,
                        'actual_manual' => $source === 'manual' ? ($realisasiManual[$krJudul] ?? null) : null,
                        'unit' => $unit,
                        'position' => $j,
                        'owner_id' => $owner,
                        'created_by' => $owner,
                    ],
                );
            }
        }

        $this->kartuGoal($now);
    }

    /**
     * Kartu todolist yang menautkan ke KR bersumber 'kartu' (Kolaborasi 5
     * kreator). Inilah demo jembatan goal → papan kerja: 3 dari 5 langkah
     * selesai, sisanya masih berjalan, jadi KR terbaca 60%.
     */
    private function kartuGoal(array $now): void
    {
        $kr = KeyResult::where('source', 'kartu')
            ->where('title', 'Kolaborasi dengan kreator lain')
            ->whereHas('objective', fn ($o) => $o->where('year', $now['year'])->where('quarter', $now['quarter']))
            ->first();

        if (! $kr) {
            return;
        }

        $penerima = User::whereIn('role', ['manager', 'it', 'admin', 'staff'])->pluck('id')->all();
        [$start] = Quarter::range($now['year'], $now['quarter']);

        // [judul, kolom, selesai?]
        $langkah = [
            ['DM & deal kreator A', 'done', true],
            ['Rekam kolaborasi kreator B', 'done', true],
            ['Publikasi kolaborasi kreator C', 'done', true],
            ['Edit kolaborasi kreator D', 'progress', false],
            ['Jadwalkan kolaborasi kreator E', 'todo', false],
        ];

        foreach ($langkah as $i => [$judul, $kolom, $selesai]) {
            $kartu = Pipeline::firstOrCreate(
                ['category' => 'todolist', 'endorse' => '[DEMO] '.$judul],
                [
                    'progress' => $kolom,
                    'account' => 'fk',
                    'payment_status' => 'belum',
                    'key_result_id' => $kr->id,
                    'assigned_to' => $penerima ? $penerima[$i % count($penerima)] : null,
                    'deadline' => $start->addDays(20 + $i * 8)->toDateString(),
                    'done' => $selesai,
                    'completed_at' => $selesai ? $start->addDays(18 + $i * 8) : null,
                    'position' => $i,
                ],
            );

            // Segarkan agar menjalankan ulang seeder mengembalikan keadaan.
            $kartu->forceFill([
                'key_result_id' => $kr->id,
                'progress' => $kolom,
                'done' => $selesai,
                'completed_at' => $selesai ? $start->addDays(18 + $i * 8) : null,
            ])->save();
        }
    }

    /**
     * Realisasi view & subscriber. Keduanya dihitung dari tabel Insight, jadi
     * demo tanpa baris di sini akan menampilkan 0% dan terbaca seolah
     * fiturnya rusak.
     */
    private function insight(array $now, array $lalu): void
    {
        // ---- Konten (view) ----
        // Disebar di dalam kuartal, bukan menumpuk di satu tanggal, supaya
        // batas kuartal ikut teruji saat dropdown diganti.
        foreach ([[$lalu, 6, 52000], [$now, 8, 61000]] as [$q, $jumlah, $viewDasar]) {
            [$start] = Quarter::range($q['year'], $q['quarter']);
            for ($i = 0; $i < $jumlah; $i++) {
                InsightContent::updateOrCreate(
                    ['platform' => 'youtube', 'content_id' => 'demo-'.$q['year'].'q'.$q['quarter'].'-'.$i],
                    [
                        'judul' => '[DEMO] Konten '.($i + 1).' '.Quarter::label($q['year'], $q['quarter']),
                        'content_type' => 'video',
                        'published_at' => $start->addDays(7 * $i + 3),
                        'views' => $viewDasar + $i * 4300,
                        'reach' => $viewDasar + $i * 4300,
                        'likes' => 900 + $i * 60,
                        'comments' => 70 + $i * 5,
                        'shares' => 40 + $i * 3,
                        'saves' => 120 + $i * 8,
                        'followers_gained' => 200 + $i * 25,
                    ],
                );
            }
        }

        // ---- Akun (subscriber) ----
        // Beberapa snapshot per akun. Ini yang membuktikan realisasi memakai
        // snapshot TERAKHIR, bukan menjumlah seluruh baris harian — kalau
        // dijumlah, angkanya melompat ke ratusan ribu dan salah total.
        $akun = [
            ['instagram', 'aipreneur.id', 'AI Preneur', [8200, 9100, 10400]],
            ['youtube', 'aipreneur', 'AI Preneur TV', [4100, 5200, 6800]],
        ];

        foreach ($akun as [$platform, $handle, $nama, $deret]) {
            foreach ([[$lalu, 0], [$lalu, 1], [$now, 2]] as $idx => [$q, $pos]) {
                [$start] = Quarter::range($q['year'], $q['quarter']);
                InsightAccount::updateOrCreate(
                    ['platform' => $platform, 'akun' => $handle, 'tanggal' => $start->addDays(30 * $idx + 5)->toDateString()],
                    [
                        'nama_akun' => $nama,
                        'followers' => $deret[$pos],
                        'media_count' => 120 + $pos * 15,
                        'reach' => 90000 + $pos * 12000,
                        'impressions' => 130000 + $pos * 15000,
                        'profile_views' => 8000 + $pos * 900,
                        'link_clicks' => 700 + $pos * 80,
                    ],
                );
            }
        }
    }

    /** Realisasi omset — transaksi pemasukan di dalam kuartal. */
    private function omset(array $now, array $lalu): void
    {
        foreach ([[$lalu, [62000000, 55000000, 48000000]], [$now, [78000000, 64000000, 51000000]]] as [$q, $nominal]) {
            [$start] = Quarter::range($q['year'], $q['quarter']);
            foreach ($nominal as $i => $amount) {
                Transaction::updateOrCreate(
                    [
                        'type' => 'pemasukan',
                        'category' => '[DEMO] Coaching',
                        'date' => $start->addDays(20 * $i + 4)->toDateString(),
                    ],
                    ['description' => 'Pembayaran batch '.($i + 1), 'amount_idr' => $amount],
                );
            }
        }
    }

    // ------------------------------------------------------------ KPI

    /** Board demo + kolomnya, mengikuti bentuk board bawaan BoardController. */
    private function board(): string
    {
        $key = 'demo_produksi';
        $owner = User::where('role', 'owner')->value('id');

        Category::updateOrCreate(
            ['key' => $key],
            ['name' => '[DEMO] Produksi Konten', 'type' => 'kanban', 'created_by' => $owner],
        );

        foreach ([['todo', 'To Do', 'bg-slate-400'], ['progress', 'Dikerjakan', 'bg-sky-500'], ['done', 'Selesai', 'bg-emerald-500']] as $i => [$k, $nama, $warna]) {
            BoardColumn::updateOrCreate(
                ['board_key' => $key, 'key' => $k],
                ['name' => $nama, 'color' => $warna, 'position' => $i],
            );
        }

        return $key;
    }

    private function targetBoard(string $board, array $now, array $lalu): void
    {
        $owner = User::where('role', 'owner')->value('id');

        // Kuartal berjalan sengaja dipatok di ATAS jumlah kartu demo yang
        // selesai, supaya barnya tidak 100% dan warna "belum tercapai" ikut
        // terlihat.
        foreach ([[$lalu, 5, 'Target percobaan kuartal lalu.'], [$now, 8, 'Naik seiring tambahan editor.']] as [$q, $target, $note]) {
            BoardQuarterTarget::updateOrCreate(
                ['board_key' => $board, 'year' => $q['year'], 'quarter' => $q['quarter']],
                ['target_done' => $target, 'note' => $note, 'created_by' => $owner],
            );
        }

        // Board bawaan 'todolist' ikut diberi target bila ada — supaya halaman
        // KPI tak menampilkan satu board bertarget di antara yang kosong.
        if (Category::where('key', 'todolist')->exists()) {
            BoardQuarterTarget::updateOrCreate(
                ['board_key' => 'todolist', 'year' => $now['year'], 'quarter' => $now['quarter']],
                ['target_done' => 6, 'note' => null, 'created_by' => $owner],
            );
        }
    }

    /**
     * Kartu demo dgn deadline & completed_at yang sengaja bervariasi:
     * tepat waktu, terlambat, lewat deadline (belum selesai), dan masih
     * berjalan. Tanpa campuran ini analitik ketepatan tak punya apa pun untuk
     * ditampilkan.
     *
     * Bentuk tiap baris: [judul, kolom, hari deadline dari awal kuartal,
     * hari selesai dari awal kuartal (null = belum selesai), peran PJ
     * (null = kartu sengaja tanpa PJ)].
     */
    private function kartu(string $board, array $now, array $lalu): void
    {
        $pembuat = User::whereIn('role', ['owner', 'manager'])->pluck('id')->all();

        // PJ ditentukan PER BARIS (bukan bergilir otomatis) supaya rapor per
        // orang punya profil yang berbeda-beda: ada yang selalu tepat waktu,
        // ada yang sering telat, dan ada kartu yang memang tak ber-PJ. Rapor
        // yang semua orangnya seragam tak memperlihatkan apakah pemeringkatan
        // & rata-rata keterlambatan benar-benar bekerja.
        $peran = User::orderBy('id')->get(['id', 'role'])->groupBy('role')
            ->map(fn ($u) => $u->pluck('id')->all());
        $pj = fn (?string $role) => $role === null ? null : ($peran[$role][0] ?? null);

        $rencana = [
            // Kuartal lalu — sebagian besar rampung, dua di antaranya telat.
            [$lalu, [
                ['Riset tema kuartal', 'done', 10, 8, 'manager'],
                ['Produksi seri edukasi', 'done', 20, 19, 'it'],
                ['Editing batch pertama', 'done', 30, 34, 'staff'],     // telat 4 hari
                ['Publikasi carousel', 'done', 40, 40, 'manager'],      // pas di hari deadline
                ['Evaluasi performa', 'done', 55, 61, 'staff'],         // telat 6 hari
                ['Arsip aset lama', 'todo', 70, null, null],            // lewat deadline & tanpa PJ
            ]],
            // Kuartal berjalan — campuran, sengaja belum mencapai target 8.
            [$now, [
                ['Script Reels seri AI', 'done', 5, 4, 'manager'],
                ['Shooting studio batch 1', 'done', 12, 11, 'it'],
                ['Editing Reels seri AI', 'done', 18, 22, 'staff'],     // telat 4 hari
                ['Thumbnail A/B test', 'done', 25, 25, 'admin'],
                ['Kolaborasi kreator', 'progress', 40, null, 'manager'],
                ['Rekap insight bulanan', 'todo', 50, null, 'staff'],
                ['Draft konten kuartal depan', 'todo', 80, null, null],   // belum ditugaskan
            ]],
        ];

        foreach ($rencana as [$q, $baris]) {
            [$start] = Quarter::range($q['year'], $q['quarter']);

            foreach ($baris as $i => [$judul, $kolom, $deadlineHari, $selesaiHari, $peranPj]) {
                $kartu = Pipeline::firstOrCreate(
                    ['category' => $board, 'endorse' => '[DEMO] '.$judul],
                    [
                        'progress' => $kolom,
                        'account' => 'fk',
                        'payment_status' => 'belum',
                        'description' => 'Kartu demo untuk menguji filter kuartal & analitik ketepatan waktu.',
                        'deadline' => $start->addDays($deadlineHari)->toDateString(),
                        'assigned_to' => $pj($peranPj),
                        // Pembuat berbeda-beda supaya "dibuat oleh" di kartu
                        // terlihat bervariasi, bukan satu nama untuk semua.
                        'created_by' => $pembuat ? $pembuat[$i % count($pembuat)] : null,
                        'done' => $selesaiHari !== null,
                        'completed_at' => $selesaiHari !== null ? $start->addDays($selesaiHari) : null,
                        'position' => $i,
                    ],
                );

                // firstOrCreate tak menyentuh baris yang sudah ada; stempel &
                // deadline tetap disegarkan supaya menjalankan ulang seeder
                // mengembalikan kartu demo ke keadaan yang dimaksud.
                $kartu->forceFill([
                    'assigned_to' => $pj($peranPj),
                    'deadline' => $start->addDays($deadlineHari)->toDateString(),
                    'completed_at' => $selesaiHari !== null ? $start->addDays($selesaiHari) : null,
                    'done' => $selesaiHari !== null,
                    'progress' => $kolom,
                ])->save();
            }
        }
    }
}
