<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_id',
        'employee_id',
        'employee_code',
        'employee_name',
        'employee_type',
        'accepted_count',
        'rejected_count',
        'gps_denied_count',
        'outside_radius_count',
        'first_attendance_at',
        'last_attendance_at',
        'base_salary',
        'allowance_amount',
        'incentive_amount',
        'ot_amount',
        'advance_deduction',
        'other_deduction',
        'gross_pay',
        'net_pay',
        'remarks',
    ];

    protected $casts = [
        'first_attendance_at' => 'datetime',
        'last_attendance_at' => 'datetime',
        'base_salary' => 'decimal:2',
        'allowance_amount' => 'decimal:2',
        'incentive_amount' => 'decimal:2',
        'ot_amount' => 'decimal:2',
        'advance_deduction' => 'decimal:2',
        'other_deduction' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
