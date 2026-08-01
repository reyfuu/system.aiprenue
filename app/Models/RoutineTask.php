<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Checklist rutinitas harian milik satu user (mirip Google Tasks).
 *  "Selesai hari ini" diturunkan dari completed_on == hari ini — lihat migrasi. */
class RoutineTask extends Model
{
    protected $fillable = ['user_id', 'title', 'completed_on', 'position'];

    protected $casts = ['completed_on' => 'date'];
}
