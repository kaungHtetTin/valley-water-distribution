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
    ];

    protected $casts = [
        'attendance_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'distance_m' => 'float',
    ];
}
