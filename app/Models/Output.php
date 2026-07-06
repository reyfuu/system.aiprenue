<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Output extends Model
{
    protected $fillable = ['name', 'color'];

    public $timestamps = true;

    public function pipelines(): BelongsToMany
    {
        return $this->belongsToMany(Pipeline::class);
    }
}
