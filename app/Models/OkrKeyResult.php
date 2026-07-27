<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OkrKeyResult extends Model
{
    protected $fillable = ['objective_id', 'title', 'completed'];

    protected $casts = [
        'completed' => 'boolean',
    ];
}
