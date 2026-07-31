<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoardColumn extends Model
{
    use Auditable, SoftDeletes;
    protected $fillable = ['board_key', 'key', 'name', 'color', 'position'];

    /** Kolom milik satu board, terurut. */
    public static function forBoard(string $boardKey)
    {
        return static::where('board_key', $boardKey)->orderBy('position')->get();
    }
}
