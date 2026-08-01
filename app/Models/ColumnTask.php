<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item checklist di dalam satu kolom Kanban, didelegasikan owner/manager ke
 *  seorang staff. Menetap: "selesai" = completed_at terisi (bukan reset harian). */
class ColumnTask extends Model
{
    protected $fillable = ['board_column_id', 'assigned_to', 'created_by', 'title', 'completed_at', 'position'];

    protected $casts = ['completed_at' => 'datetime'];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
