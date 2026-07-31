<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Absence extends Model
{
    use Auditable, SoftDeletes;
    protected $fillable = [
        'user_id', 'type', 'start_date', 'end_date', 'reason', 'attachment_path', 'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /** Jenis pengajuan (key => label) — dipakai dropdown form & badge tabel. */
    public const TYPES = ['cuti' => 'Cuti', 'sakit' => 'Sakit', 'izin' => 'Izin'];

    /** Status persetujuan. */
    public const STATUSES = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
