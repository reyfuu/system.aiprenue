<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pipeline extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category', 'jenis', 'account', 'assigned_to', 'created_by', 'coaching', 'speaker', 'endorse', 'description', 'progress',
        'tanggal_posting', 'tanggal_payment', 'deadline', 'payment_status',
        'amount_idr', 'amount_usd', 'dp1', 'dp2', 'dp3', 'notes', 'link', 'todos', 'labels', 'done',
        'completed_at', 'archived_at', 'kontak_wa', 'kontak_gmail', 'kontak_ig',
    ];

    protected $casts = [
        'tanggal_posting' => 'date',
        'tanggal_payment' => 'date',
        'deadline' => 'date',
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
        'amount_idr' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'dp1' => 'decimal:2',
        'dp2' => 'decimal:2',
        'dp3' => 'decimal:2',
        'todos' => 'array',
        'labels' => 'array',
        'done' => 'boolean',
    ];

    public function outputs(): BelongsToMany
    {
        return $this->belongsToMany(Output::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Pembuat kartu. Terpisah dari assignee: yang membuat dan yang mengerjakan
     *  sering bukan orang yang sama. null utk kartu lama (kolomnya baru ada). */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Ketepatan waktu kartu — dasar analitik "sering terlambat / tepat waktu".
     *
     *  'tepat'      selesai pada atau sebelum deadline
     *  'terlambat'  selesai sesudah deadline
     *  'lewat'      BELUM selesai & deadline sudah lewat (masih berjalan)
     *  null         tak bisa dinilai: tanpa deadline, atau belum selesai &
     *               deadline belum tiba
     *
     *  Kartu tanpa deadline sengaja null, bukan 'tepat'. Menghitungnya sbg
     *  tepat waktu akan menggelembungkan persentase ketepatan dgn kartu yang
     *  tak pernah punya janji waktu untuk ditepati.
     *
     *  Perbandingannya per TANGGAL, bukan per detik: deadline disimpan sbg
     *  date (tengah malam), jadi kartu yang rampung sore hari di tanggal
     *  deadline-nya akan terbaca terlambat kalau dibandingkan sbg timestamp.
     */
    public function ketepatan(): ?string
    {
        if ($this->deadline === null) {
            return null;
        }

        if ($this->completed_at !== null) {
            return $this->completed_at->startOfDay()->lessThanOrEqualTo($this->deadline->startOfDay())
                ? 'tepat' : 'terlambat';
        }

        return $this->deadline->startOfDay()->lessThan(now()->startOfDay()) ? 'lewat' : null;
    }

    /** Komentar kartu (terbaru dulu saat ditampilkan). */
    public function comments(): HasMany
    {
        return $this->hasMany(PipelineComment::class);
    }

    /** Lampiran file kartu. */
    public function attachments(): HasMany
    {
        return $this->hasMany(PipelineAttachment::class);
    }

    /** Kategori/board dinamis dari tabel categories: ['key' => 'Name'].
     *  $type = 'kanban' | 'pipeline' untuk memfilter per modul (null = semua). */
    public static function categories(?string $type = null): array
    {
        return Category::when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('id')->pluck('name', 'key')->all();
    }

    /** Jenis deal — dulu board terpisah (endorse/coaching/agensi/speaker), kini
     *  atribut kartu di board `sales`. String biasa (bukan enum) supaya bisa
     *  ditambah tanpa migrasi. null = kartu board kanban (bukan deal). */
    public const JENIS = [
        'endorse' => 'Endorse',
        'coaching_1on1' => 'Coaching 1-on-1',
        'coaching_perusahaan' => 'Coaching Perusahaan',
        'agensi' => 'Agensi',
        'speaker' => 'Speaker',
    ];

    /** Account = enum('fk','ai_preneur') di tabel pipelines.
     *  Menambah pilihan di sini WAJIB dibarengi migrasi ubah enum, kalau tidak
     *  insert-nya ditolak MySQL. */
    public const ACCOUNTS = [
        'fk' => 'FK', 'ai_preneur' => 'AI Preneur',
    ];

    /** Warna badge per account (kelas Tailwind). */
    public const ACCOUNT_COLORS = [
        'fk' => 'bg-brand-600 text-white',
        'ai_preneur' => 'bg-slate-500 text-white',
    ];

    public const PROGRESS = [
        'script' => 'Script', 'editing' => 'Editing', 'progress' => 'Progress',
        'pending' => 'Pending', 'done' => 'Done',
    ];

    public const PAYMENT = ['belum' => 'Belum', 'dp' => 'DP', 'lunas' => 'Lunas'];
}
