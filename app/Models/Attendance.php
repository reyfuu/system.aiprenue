<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'work_date',
        'check_in',
        'check_out',
        'source',
        'note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isWorkday(): bool
    {
        return $this->work_date && ! $this->work_date->isWeekend();
    }

    public function workingMinutes(): int
    {
        if (! $this->check_in || ! $this->check_out) {
            return 0;
        }

        return (int) (max(0, $this->check_out->timestamp - $this->check_in->timestamp) / 60);
    }

    public function lateMinutes(): int
    {
        if (! $this->check_in) {
            return 0;
        }

        $start = Carbon::parse($this->work_date->toDateString() . ' 09:00:00');
        $actual = Carbon::parse($this->check_in);

        return (int) max(0, ($actual->timestamp - $start->timestamp) / 60);
    }

    public function overtimeMinutes(): int
    {
        if (! $this->check_out) {
            return 0;
        }

        $end = Carbon::parse($this->work_date->toDateString() . ' 18:00:00');
        $actual = Carbon::parse($this->check_out);

        return (int) max(0, ($actual->timestamp - $end->timestamp) / 60);
    }
}
