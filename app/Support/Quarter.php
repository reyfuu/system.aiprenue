<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Aritmetika kuartal, terkumpul di satu tempat.
 *
 *  Ada tiga pemakai yang harus sepakat soal "kuartal itu apa": filter Kanban,
 *  target board, dan OKR. Kalau masing-masing menghitung sendiri, cukup satu
 *  yang memakai batas akhir eksklusif untuk membuat angka realisasi OKR beda
 *  dgn jumlah kartu di board yang sama — selisih sehari yang sangat sulit
 *  dilacak. Jadi satu sumber, dipakai semuanya.
 *
 *  Batasnya INKLUSIF di kedua ujung (1 Jan 00:00 s/d 31 Mar 23:59:59), supaya
 *  bisa dipakai apa adanya oleh whereDate() maupun whereBetween() pada kolom
 *  timestamp tanpa perlu penyesuaian di tiap pemanggil.
 */
final class Quarter
{
    /** Kuartal yang sedang berjalan, sebagai ['year' => 2026, 'quarter' => 3]. */
    public static function current(): array
    {
        $now = CarbonImmutable::now();

        return ['year' => (int) $now->year, 'quarter' => (int) $now->quarter];
    }

    /** Kuartal dari sebuah tanggal. null bila tanggalnya kosong — dipakai
     *  kartu tanpa deadline, yang memang tidak masuk kuartal mana pun. */
    public static function of(?string $date): ?array
    {
        if (! filled($date)) {
            return null;
        }

        $d = CarbonImmutable::parse($date);

        return ['year' => (int) $d->year, 'quarter' => (int) $d->quarter];
    }

    /** Batas awal & akhir kuartal (inklusif). */
    public static function range(int $year, int $quarter): array
    {
        $start = CarbonImmutable::create($year, ($quarter - 1) * 3 + 1, 1)->startOfDay();

        return [$start, $start->addMonths(3)->subSecond()];
    }

    /** Label singkat untuk UI: "Q3 2026". */
    public static function label(int $year, int $quarter): string
    {
        return 'Q'.$quarter.' '.$year;
    }

    /** Daftar kuartal untuk dropdown: dari `$back` kuartal lalu s/d `$ahead`
     *  ke depan, terbaru dulu. Rentangnya bergerak mengikuti hari ini, jadi
     *  daftarnya tak pernah basi tanpa disentuh. */
    public static function options(int $back = 7, int $ahead = 1): array
    {
        $now = CarbonImmutable::now()->startOfQuarter();
        $out = [];
        for ($i = $ahead; $i >= -$back; $i--) {
            $q = $now->addQuarters($i);
            $out[] = [
                'year' => (int) $q->year,
                'quarter' => (int) $q->quarter,
                'key' => $q->year.'-Q'.$q->quarter,
                'label' => self::label((int) $q->year, (int) $q->quarter),
            ];
        }

        return $out;
    }

    /** Urai 'YYYY-Qn' dari querystring. null bila bentuknya tak sesuai —
     *  parameter ngawur diabaikan, bukan bikin halaman error (sikap yang sama
     *  dgn filter `jenis` di PipelineController). */
    public static function parse(?string $key): ?array
    {
        if (! is_string($key) || ! preg_match('/^(\d{4})-Q([1-4])$/', $key, $m)) {
            return null;
        }

        return ['year' => (int) $m[1], 'quarter' => (int) $m[2]];
    }
}
