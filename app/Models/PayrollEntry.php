<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollEntry extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'payroll_period_id',
        'user_id',
        'work_days',
        'attendance_days',
        'absent_days',
        'late_minutes',
        'overtime_minutes',
        'base_salary',
        'allowance',
        'overtime_rate',
        'overtime_amount',
        'gross_salary',
        'deductions',
        'net_salary',
        'notes',
    ];

    protected $casts = [
        'work_days' => 'integer',
        'attendance_days' => 'integer',
        'absent_days' => 'integer',
        'late_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'base_salary' => 'decimal:2',
        'allowance' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

