<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Output extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'color'];

    public $timestamps = true;

    public function pipelines(): BelongsToMany
    {
        return $this->belongsToMany(Pipeline::class);
    }
}
