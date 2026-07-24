<?php

namespace App\Models;

use App\Support\OkrMetrics;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu Key Result: bagian OKR yang benar-benar terukur. */
class KeyResult extends Model
{
    protected $fillable = [
        'objective_id', 'title', 'source', 'metric', 'target', 'actual_manual', 'unit', 'position',
        'owner_id', 'created_by',
    ];

    protected $casts = [
        'target' => 'decimal:2',
        'actual_manual' => 'decimal:2',
        'position' => 'integer',
    ];

    /** Dari mana realisasinya berasal.
     *  `auto`   — dihitung dari Insight/Pembukuan, tak bisa diketik tangan.
     *  `manual` — diperbarui sendiri; untuk target tanpa sumber data. */
    public const SOURCES = ['auto' => 'Otomatis', 'manual' => 'Manual'];

    /** Satuan, dipakai UI untuk memformat angka. */
    public const UNITS = ['angka' => 'Angka', 'rupiah' => 'Rupiah', 'persen' => 'Persen'];

    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Penanggung jawab — yang mengejar angka ini. Terpisah dari creator:
     *  yang menuliskan target dan yang mengejarnya sering bukan orang yang sama. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Realisasi KR ini.
     *
     *  KR `auto` mengambil dari $realisasi yang sudah dihitung sekali untuk
     *  seluruh kuartal — bukan memanggil OkrMetrics sendiri, yang akan berarti
     *  satu rangkaian query per KR.
     *
     *  Metrik yang tak dikenal mengembalikan 0. Itu keadaan yang seharusnya
     *  tak pernah terjadi (validasi controller menjaganya), tapi kalau terjadi
     *  lebih baik 0 daripada halaman pecah.
     */
    public function actual(array $realisasi = []): float
    {
        if ($this->source === 'auto') {
            return (float) ($realisasi[$this->metric] ?? OkrMetrics::realisasi(
                $this->objective->year, $this->objective->quarter
            )[$this->metric] ?? 0);
        }

        return (float) ($this->actual_manual ?? 0);
    }

    /**
     * Capaian dalam persen, atau null bila targetnya belum ditetapkan.
     *
     *  null — BUKAN 0 — karena persentase terhadap target nol tak punya arti,
     *  dan menampilkannya sbg 0% terbaca seolah timnya gagal padahal targetnya
     *  memang belum ada. Aturan yang sama dipakai di seluruh modul KPI.
     *
     *  Tidak dibatasi 100%: capaian 130% memang layak terlihat. Pembatasan
     *  hanya terjadi saat KR ini dirata-rata ke progress Objective —
     *  lihat Objective::progress().
     */
    public function percent(array $realisasi = []): ?float
    {
        $target = (float) $this->target;

        return $target > 0 ? round($this->actual($realisasi) / $target * 100, 1) : null;
    }
}
