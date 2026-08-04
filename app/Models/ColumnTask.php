<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item checklist di dalam satu kolom Kanban, didelegasikan owner/manager ke
 *  seorang staff. Reset harian: "selesai" = completed_on == hari ini, jadi
 *  centang kosong lagi tiap lewat jam 12 malam (tanpa cron). */
class ColumnTask extends Model
{
    /**
     * Cache cek skema: apakah kolom `completed_on` sudah ada.
     * Jika tidak ada (legacy DB), fallback ke `completed_at`.
     */
    protected static ?bool $hasCompletedOnColumn = null;

    // Tambahkan fallback compatibility agar tidak error saat deploy di DB lama.
    protected $fillable = ['board_column_id', 'assigned_to', 'created_by', 'title', 'completed_on', 'completed_at', 'position'];

    protected $casts = [
        'completed_on' => 'date',
        'completed_at' => 'datetime',
    ];

    public static function completedField(): string
    {
        return self::hasCompletedOnColumn() ? 'completed_on' : 'completed_at';
    }

    public static function hasCompletedOnColumn(): bool
    {
        if (self::$hasCompletedOnColumn === null) {
            self::$hasCompletedOnColumn = \Illuminate\Support\Facades\Schema::hasColumn('column_tasks', 'completed_on');
        }

        return self::$hasCompletedOnColumn;
    }

    public function isDoneToday(): bool
    {
        $completedValue = $this->getAttribute('completed_on');

        if (! $completedValue && ! self::hasCompletedOnColumn()) {
            $completedValue = $this->getAttribute('completed_at');
        }

        return $completedValue ? \Illuminate\Support\Carbon::parse($completedValue)->isToday() : false;
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
