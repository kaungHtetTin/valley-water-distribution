<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['hire_date' => 'date', 'is_active' => 'boolean'];

    public function assignedRoute()
    {
        return $this->belongsTo(DeliveryRoute::class, 'assigned_route_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'assigned_driver_id');
    }
}
