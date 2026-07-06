<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pipeline extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category', 'account', 'coaching', 'speaker', 'endorse', 'progress',
        'tanggal_posting', 'tanggal_payment', 'payment_status',
        'amount_idr', 'amount_usd', 'notes', 'ke_gilang', 'catatan',
        'created_by', 'updated_by',
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

    public const CATEGORIES = [
        'endorse' => 'Endorse', 'agensi' => 'Agensi',
        'coaching' => 'Coaching', 'speaker' => 'Speaker',
    ];
    public const ACCOUNTS = ['fk' => 'FK', 'ai_preneur' => 'AI Preneur'];

    /** Warna badge per account (kelas Tailwind). */
    public const ACCOUNT_COLORS = [
        'fk'         => 'bg-brand-600 text-white',
        'ai_preneur' => 'bg-violet-600 text-white',
    ];
    public const PROGRESS = [
        'script' => 'Script', 'editing' => 'Editing', 'progress' => 'Progress',
        'done' => 'Done', 'pending' => 'Pending', 'tentatif' => 'Tentatif',
    ];
    public const PAYMENT = ['belum' => 'Belum', 'dp' => 'DP', 'lunas' => 'Lunas'];
    public const KE_GILANG = ['belum' => 'Belum', 'sudah' => 'Sudah', 'done' => 'DONE'];
}
