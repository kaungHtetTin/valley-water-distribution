<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['capacity' => 'decimal:2', 'is_active' => 'boolean'];

    public function assignedDriver()
    {
        return $this->belongsTo(Employee::class, 'assigned_driver_id');
    }
}
