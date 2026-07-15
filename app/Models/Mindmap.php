<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mindmap extends Model
{
    protected $fillable = ['user_id', 'title', 'data'];

    protected $casts = ['data' => 'array']; // JSON node mind-elixir ↔ array PHP

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
