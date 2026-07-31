<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/** Satu Objective kuartalan: tujuan utama, termasuk target omzet bila ada. */
class Objective extends Model
{
    use Auditable, SoftDeletes;

    /**
     * Dulu `key_results.objective_id` cascadeOnDelete di skema — DB yang
     * membereskan anak saat induk dihapus. Soft delete = UPDATE `deleted_at`,
     * bukan DELETE, jadi FK cascade tidak pernah terpicu. Kita tiru manual:
     * hapus Objective → ikut soft-delete semua Key Result-nya; restore
     * Objective → kembalikan (hanya) yang ikut hilang bareng dia, bukan yang
     * memang sudah dihapus terpisah sebelumnya.
     */
    protected static function booted(): void
    {
        static::deleting(function (Objective $objective) {
            if ($objective->isForceDeleting()) {
                return;
            }
            $objective->keyResults()->delete();
        });

        static::restoring(function (Objective $objective) {
            $objective->keyResults()->onlyTrashed()->restore();
        });
    }

    protected $fillable = [
        'year', 'quarter', 'title', 'description', 'priority',
        'omset_target', 'omset_owner_id', 'position', 'created_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'quarter' => 'integer',
        'priority' => 'array',
        'omset_target' => 'decimal:2',
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

    /** PIC yang bertanggung jawab mengejar target omzet Objective. */
    public function omsetOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'omset_owner_id');
    }

    /**
     * Progress Objective = rata-rata target omzet (bila ada) dan persen Key
     * Result-nya. Setiap kontribusi DIBATASI 100% LEBIH DULU.
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

        $omsetTarget = (float) ($this->omset_target ?? 0);
        if ($omsetTarget > 0) {
            $pName = is_array($this->priority) ? ($this->priority['name'] ?? '') : '';
            $realisasiOmset = match (strtoupper((string) $pName)) {
                'FK' => (float) ($realisasi['omset_fk'] ?? 0),
                'AIPRENEUR' => (float) ($realisasi['omset_aipreneur'] ?? 0),
                default => (float) ($realisasi['omset'] ?? 0),
            };
            $persen->prepend(min(100, $realisasiOmset / $omsetTarget * 100));
        }

        return $persen->isEmpty() ? null : round($persen->avg(), 1);
    }

    /** Objective satu kuartal beserta Key Result-nya, terurut. */
    public static function forQuarter(int $year, int $quarter): Collection
    {
        return static::with(['keyResults', 'creator', 'omsetOwner'])
            ->where('year', $year)->where('quarter', $quarter)
            ->orderBy('position')->orderBy('id')->get();
    }
}
