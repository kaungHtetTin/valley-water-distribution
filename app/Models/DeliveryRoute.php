<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryRoute extends Model
{
    use SoftDeletes;

    protected $table = 'routes';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'route_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'assigned_route_id');
    }
}
