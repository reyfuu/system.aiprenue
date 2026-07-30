<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use Auditable;
    protected $fillable = ['type', 'category', 'description', 'amount_idr', 'date', 'bukti_path'];

    protected $casts = [
        'date' => 'date',
        'amount_idr' => 'decimal:2',
    ];

    public const TYPES = ['pemasukan' => 'Pemasukan', 'pengeluaran' => 'Pengeluaran'];

    /** Kategori umum transaksi — biar bisa dipilih dari dropdown. */
    public const CATEGORIES = [
        'Omzet',
        'Biaya Operasional',
        'Gaji / Honor',
        'Marketing / Iklan',
        'Sewa / Langganan',
        'Peralatan',
        'Transportasi',
        'Lainnya',
    ];
}
