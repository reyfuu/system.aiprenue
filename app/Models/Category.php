<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['key', 'name'];

    public function pipelines()
    {
        return $this->hasMany(Pipeline::class, 'category', 'key');
    }
}
