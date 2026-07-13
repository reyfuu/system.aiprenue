<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Komentar pada satu kartu pipeline.
class PipelineComment extends Model
{
    protected $fillable = ['pipeline_id', 'user_id', 'body']; // field yg boleh diisi massal

    // Penulis komentar
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
