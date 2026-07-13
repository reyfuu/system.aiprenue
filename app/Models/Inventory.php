<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = ['name', 'qty', 'unit_value_idr', 'month'];

    protected $casts = [
        'month' => 'date',
        'unit_value_idr' => 'decimal:2',
    ];

    public function getTotalValueAttribute(): float
    {
        return $this->qty * (float) $this->unit_value_idr;
    }
}
