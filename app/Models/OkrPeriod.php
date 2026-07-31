<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Judul OKR perusahaan per (tahun, kuartal). Baris hanya dibuat saat judul
 * diedit; kalau kosong, OkrController::index() memakai judul default.
 */
class OkrPeriod extends Model
{
    use Auditable; // edit judul OKR ikut tercatat di audit log

    protected $fillable = ['year', 'quarter', 'title'];
}
