<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BoardColumn;

class Pipeline extends Model
{
    use SoftDeletes;
    protected static function booted(): void
    {
        static::saving(function (Pipeline $pipeline) {
            if (! $pipeline->isDirty('progress')) {
                return;
            }
            $col = BoardColumn::where('board_key', $pipeline->category)
                ->where('key', $pipeline->progress)
                ->first();
            if ($col) {
                $name = mb_strtolower($col->name);
                $isDone = str_starts_with($name, 'done') || str_starts_with($name, 'done ')
                    || $col->key === 'done' || $col->key === 'deal' || $col->key === 'closing';
                if ($isDone) {
                    $pipeline->done = 1;
                    $pipeline->completed_at = $pipeline->completed_at ?? now();
                } else {
                    $pipeline->done = 0;
                    $pipeline->completed_at = null;
                }
            }
        });
    }

    protected $fillable = [
        'category', 'account', 'assigned_to', 'coaching', 'speaker', 'endorse', 'description', 'progress',
        'tanggal_posting', 'tanggal_payment', 'deadline', 'payment_status',
        'amount_idr', 'amount_usd', 'notes', 'link', 'todos', 'labels', 'ke_gilang', 'catatan',
        'archived_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal_posting' => 'date',
        'tanggal_payment' => 'date',
        'deadline' => 'date',
        'archived_at' => 'datetime',
        'amount_idr' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'todos' => 'array',
        'labels' => 'array',
    ];

    public function outputs(): BelongsToMany
    {
        return $this->belongsToMany(Output::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Komentar kartu (terbaru dulu saat ditampilkan). */
    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PipelineComment::class);
    }

    /** Lampiran file kartu. */
    public function attachments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PipelineAttachment::class);
    }

    /** Kategori/board dinamis dari tabel categories: ['key' => 'Name']. */
    public static function categories(): array
    {
        return Category::orderBy('id')->pluck('name', 'key')->all();
    }

    public const ACCOUNTS = ['fk' => 'FK', 'ai_preneur' => 'AI Preneur'];

    /** Warna badge per account (kelas Tailwind). */
    public const ACCOUNT_COLORS = [
        'fk'         => 'bg-brand-600 text-white',
        'ai_preneur' => 'bg-violet-600 text-white',
    ];
    public const PROGRESS = [
        'script' => 'Script', 'editing' => 'Editing', 'progress' => 'Progress',
        'pending' => 'Pending', 'done' => 'Done',
    ];
    public const PAYMENT = ['belum' => 'Belum', 'dp' => 'DP', 'lunas' => 'Lunas'];
    public const KE_GILANG = ['belum' => 'Belum', 'sudah' => 'Sudah', 'done' => 'DONE'];
}
