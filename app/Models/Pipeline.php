<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pipeline extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account', 'coaching', 'speaker', 'endorse', 'progress',
        'tanggal_posting', 'tanggal_payment', 'payment_status',
        'amount_idr', 'amount_usd', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal_posting' => 'date',
        'tanggal_payment' => 'date',
        'amount_idr' => 'decimal:2',
        'amount_usd' => 'decimal:2',
    ];

    public function outputs(): BelongsToMany
    {
        return $this->belongsToMany(Output::class);
    }

    public const ACCOUNTS = ['fk' => 'FK', 'ai_preneur' => 'AI Preneur'];
    public const PROGRESS = ['editing' => 'Editing', 'progress' => 'Progress', 'done' => 'Done'];
    public const PAYMENT = ['belum' => 'Belum', 'dp' => 'DP', 'lunas' => 'Lunas'];
}
