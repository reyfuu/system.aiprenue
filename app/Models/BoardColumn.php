<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardColumn extends Model
{
    protected $fillable = ['board_key', 'key', 'name', 'color', 'position'];

    /** Kolom milik satu board, terurut. */
    public static function forBoard(string $boardKey)
    {
        return static::where('board_key', $boardKey)->orderBy('position')->get();
    }
}
