<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryTrip extends Model
{
    protected $guarded = [];

    protected $casts = [
        'planned_date' => 'date',
        'total_quantity' => 'decimal:2',
    ];

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'trip_id');
    }
}
