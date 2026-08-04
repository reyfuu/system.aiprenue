<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item checklist di dalam satu kolom Kanban, didelegasikan owner/manager ke
 *  seorang staff. Reset harian: "selesai" = completed_on == hari ini, jadi
 *  centang kosong lagi tiap lewat jam 12 malam (tanpa cron). */
class ColumnTask extends Model
{
    protected $fillable = ['board_column_id', 'assigned_to', 'created_by', 'title', 'completed_on', 'position'];

    protected $casts = ['completed_on' => 'date'];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
