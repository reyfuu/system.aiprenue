<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use Auditable;
    protected $fillable = ['type', 'category', 'description', 'amount_idr', 'date'];

    protected $casts = [
        'date' => 'date',
        'amount_idr' => 'decimal:2',
    ];

    public const TYPES = ['pemasukan' => 'Pemasukan', 'pengeluaran' => 'Pengeluaran'];
}
