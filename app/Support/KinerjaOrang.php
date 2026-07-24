<?php

namespace App\Support;

use App\Http\Controllers\KpiController;
use App\Models\Category;
use App\Models\Pipeline;
use App\Models\User;

/**
 * Rekap kerja per orang pada satu kuartal.
 *
 *  Basisnya `assigned_to` (PJ kartu), bukan `created_by`: yang dinilai adalah
 *  orang yang mengerjakan dan yang kena deadline, bukan yang menuangkan
 *  pekerjaan ke sistem.
 *
 *  Kuartal ditentukan DEADLINE kartu — sama dengan seluruh modul KPI, supaya
 *  angka di halaman KPI, panel Kanban, dan rapor ini tak pernah berselisih.
 *
 *  Rumus tepat/terlambat TIDAK ditulis ulang di sini; memakai
 *  Pipeline::ketepatan() lewat KpiController::hitungKetepatan(). Dua rumus
 *  untuk satu pertanyaan adalah cara paling mudah membuat dua halaman
 *  menampilkan angka berbeda.
 */
final class KinerjaOrang
{
    /**
     * Satu baris per orang yang punya minimal satu kartu di kuartal itu, plus
     * satu baris "Belum ditugaskan" bila ada kartu tanpa PJ.
     *
     *  User tanpa kartu sengaja TIDAK dirender — baris nol di semua kolom
     *  hanya jadi kebisingan dan menenggelamkan yang benar-benar bekerja.
     *
     *  Kartu tanpa PJ juga tidak dibuang. Kalau dibuang, jumlah kartu di rapor
     *  ini tak akan pernah cocok dengan jumlah kartu di halaman board, dan
     *  selisihnya jadi misteri yang tak ada penjelasannya di layar.
     */
    public static function untukKuartal(int $year, int $quarter): array
    {
        [$start, $end] = Quarter::range($year, $quarter);

        // Hanya board kanban: kartu Sales adalah deal, bukan penugasan kerja.
        $boardKanban = Category::where('type', 'kanban')->pluck('key');

        $kartu = Pipeline::whereIn('category', $boardKanban)
            ->whereBetween('deadline', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'assigned_to', 'deadline', 'completed_at']);

        $nama = User::whereIn('id', $kartu->pluck('assigned_to')->filter()->unique())
            ->get(['id', 'name', 'role'])->keyBy('id');

        $baris = $kartu->groupBy('assigned_to')->map(function ($milik, $userId) use ($nama) {
            // groupBy pada nilai null menghasilkan key string kosong, bukan null.
            $user = $userId === '' ? null : $nama->get((int) $userId);

            return self::baris(
                $user?->id,
                $user?->name ?? 'Belum ditugaskan',
                $user?->role,
                $milik,
            );
        })->values();

        // Terlambat terbanyak di atas: rapor ini dibaca untuk menemukan yang
        // butuh perhatian, bukan untuk memberi selamat. Baris "belum
        // ditugaskan" (user_id null) selalu didorong ke dasar — ia bukan orang
        // dan tak layak diperingkat bersama orang.
        return $baris->sortBy([
            fn ($a, $b) => ($a['user_id'] === null ? 1 : 0) <=> ($b['user_id'] === null ? 1 : 0),
            fn ($a, $b) => $b['terlambat'] <=> $a['terlambat'],
            fn ($a, $b) => $b['total'] <=> $a['total'],
        ])->values()->all();
    }

    /** Satu baris rapor. */
    private static function baris(?int $userId, string $nama, ?string $role, $kartu): array
    {
        $ketepatan = KpiController::hitungKetepatan($kartu);

        return [
            'user_id' => $userId,
            'nama' => $nama,
            'role' => $role,
            'total' => $kartu->count(),
            'selesai' => $kartu->whereNotNull('completed_at')->count(),
            'tepat' => $ketepatan['tepat'],
            'terlambat' => $ketepatan['terlambat'],
            'lewat' => $ketepatan['lewat'],
            'persen_tepat' => $ketepatan['persen_tepat'],
            'rata_telat' => self::rataKeterlambatan($kartu),
        ];
    }

    /**
     * Rata-rata keterlambatan dalam hari penuh, HANYA dari kartu yang benar-
     * benar terlambat.
     *
     *  Kalau semua kartu ikut dihitung, kartu yang selesai lebih awal bernilai
     *  negatif dan menarik rata-ratanya ke bawah — seseorang yang telat 20 hari
     *  sekali tapi sering selesai awal akan terbaca "rata-rata tepat waktu".
     *  Yang ingin dijawab di sini adalah "kalau telat, biasanya telat berapa
     *  lama", jadi hanya yang telat yang boleh masuk.
     *
     *  null = tak pernah terlambat di kuartal itu (UI menampilkannya sbg '—',
     *  bukan 0 hari, karena "tak pernah telat" bukan "telat nol hari").
     */
    private static function rataKeterlambatan($kartu): ?float
    {
        $selisih = collect($kartu)
            ->filter(fn (Pipeline $p) => $p->ketepatan() === 'terlambat')
            ->map(fn (Pipeline $p) => $p->deadline->startOfDay()->diffInDays($p->completed_at->startOfDay()));

        return $selisih->isEmpty() ? null : round($selisih->avg(), 1);
    }
}
