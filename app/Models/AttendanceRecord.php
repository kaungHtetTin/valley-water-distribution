<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_location_id',
        'employee_id',
        'entered_employee_code',
        'submitted_token',
        'attendance_at',
        'latitude',
        'longitude',
        'distance_m',
        'status',
        'rejection_reason',
        'late_minutes', 'late_fine', 'salary_snapshot', 'start_time_snapshot',
    ];

    protected $casts = [
        'attendance_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'distance_m' => 'float',
        'late_minutes' => 'integer',
        'late_fine' => 'float',
        'salary_snapshot' => 'float',
    ];
}
