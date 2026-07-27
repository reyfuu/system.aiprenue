<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** Satu Objective kuartalan: kalimat tujuan yang tidak diukur langsung —
 *  yang terukur adalah Key Result di bawahnya. */
class Objective extends Model
{
    protected $fillable = ['year', 'quarter', 'title', 'description', 'position', 'created_by'];

    protected $casts = [
        'year' => 'integer',
        'quarter' => 'integer',
        'position' => 'integer',
    ];

    public function keyResults(): HasMany
    {
        return $this->hasMany(KeyResult::class)->orderBy('position')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Progress Objective = rata-rata persen Key Result-nya, TIAP KR DIBATASI
     * 100% LEBIH DULU.
     *
     *  Batas itu yang membuat angkanya jujur. Tanpa dibatasi, satu KR yang
     *  300% menutupi dua KR yang 0% dan Objective terbaca "tercapai" padahal
     *  dua pertiganya belum jalan sama sekali. Persen tiap KR tetap
     *  ditampilkan apa adanya di UI — yang dibatasi hanya kontribusinya ke
     *  rata-rata ini.
     *
     *  KR tanpa target (persen null) DIABAIKAN dari rata-rata, bukan dihitung
     *  0: "belum ditetapkan" bukan "belum tercapai". Bila seluruh KR begitu,
     *  hasilnya null — Objective ini memang belum bisa dinilai.
     *
     *  $realisasi dioper dari pemanggil (dihitung sekali per kuartal), bukan
     *  diambil sendiri per KR.
     */
    public function progress(array $realisasi): ?float
    {
        $persen = $this->keyResults
            ->map(fn (KeyResult $kr) => $kr->percent($realisasi))
            ->filter(fn ($p) => $p !== null)
            ->map(fn ($p) => min(100, $p));

        return $persen->isEmpty() ? null : round($persen->avg(), 1);
    }

    /** Objective satu kuartal beserta Key Result-nya, terurut. */
    public static function forQuarter(int $year, int $quarter): Collection
    {
        return static::with(['keyResults', 'creator'])
            ->where('year', $year)->where('quarter', $quarter)
            ->orderBy('position')->orderBy('id')->get();
    }
}
