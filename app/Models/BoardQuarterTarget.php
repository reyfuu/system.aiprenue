<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Target KPI satu board untuk satu kuartal: berapa kartu harus selesai. */
class BoardQuarterTarget extends Model
{
    protected $fillable = ['board_key', 'year', 'quarter', 'target_done', 'note', 'created_by'];

    protected $casts = [
        'year' => 'integer',
        'quarter' => 'integer',
        'target_done' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Target board pada kuartal tertentu, atau null bila belum ditetapkan.
     *  Sengaja null (bukan objek bertarget 0): "belum ditetapkan" dan "target
     *  nol" adalah dua keadaan berbeda, dan UI menampilkannya berbeda pula. */
    public static function for(string $boardKey, int $year, int $quarter): ?self
    {
        return static::where('board_key', $boardKey)
            ->where('year', $year)->where('quarter', $quarter)->first();
    }
}
