<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Judul OKR perusahaan per (tahun, kuartal). Baris hanya dibuat saat judul
 * diedit; kalau kosong, OkrController::index() memakai judul default.
 */
class OkrPeriod extends Model
{
    use Auditable, SoftDeletes; // edit judul OKR ikut tercatat di audit log

    protected $fillable = ['year', 'quarter', 'title', 'omset_target'];
    protected $casts = [
        'year' => 'integer',
        'quarter' => 'integer',
        'omset_target' => 'decimal:2',
    ];
}
