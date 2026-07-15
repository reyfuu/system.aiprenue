<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

// Lampiran file pada satu kartu pipeline.
class PipelineAttachment extends Model
{
    protected $fillable = ['pipeline_id', 'user_id', 'path', 'name', 'mime', 'size']; // field massal

    protected $appends = ['url']; // sertakan URL publik saat di-serialize

    // URL publik file (butuh `php artisan storage:link`)
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    // Pengunggah
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
