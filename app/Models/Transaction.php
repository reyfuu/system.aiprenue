<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = ['type', 'category', 'description', 'amount_idr', 'date'];

    protected $casts = [
        'date' => 'date',
        'amount_idr' => 'decimal:2',
    ];

    public const TYPES = ['pemasukan' => 'Pemasukan', 'pengeluaran' => 'Pengeluaran'];
}
