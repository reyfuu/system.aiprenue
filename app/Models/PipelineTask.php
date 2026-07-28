<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineTask extends Model
{
    protected $fillable = ['pipeline_id', 'title', 'assigned_to', 'deadline', 'completed_at', 'position'];
    protected $casts = ['deadline' => 'date', 'completed_at' => 'datetime'];

    public function pipeline(): BelongsTo { return $this->belongsTo(Pipeline::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
}
